<?php

namespace App\Services\Finance;

use App\Models\Entity;
use App\Models\FinanceTransaction;
use App\Models\Organization;
use App\Models\Relationship;
use App\Models\RelationshipType;
use Illuminate\Support\Facades\DB;

/**
 * Turns imported contributions into graph structure: promotes a committee (and its significant
 * donors) into canonical entities and creates ONE aggregated "donated to" relationship per donor
 * (total $ + count across the period), rather than an edge per transaction. This is what makes the
 * money legible as a network without flooding the graph with duplicate edges.
 */
class FinanceNetworkBuilder
{
    /** Auto-build defaults (overridable per organization via settings.finance_network). */
    public const DEFAULT_MIN_COMMITTEE_TOTAL = 10000;
    public const DEFAULT_MIN_DONOR_TOTAL = 1000;
    public const DEFAULT_MAX_DONORS_PER_COMMITTEE = 50;

    /**
     * @return array{committee_entity_id:int, donors_promoted:int, connections:int, transactions_linked:int}
     */
    public function buildFromCommittee(string $committeeName, float $minTotal = 1000, int $maxDonors = 200): array
    {
        $orgId = Organization::currentId();
        $donatedTo = RelationshipType::where('name', 'donated_to')->first();

        return DB::transaction(function () use ($committeeName, $minTotal, $maxDonors, $orgId, $donatedTo): array {
            // 1. Committee → Organization entity.
            $committee = $this->findOrCreateEntity($committeeName, 'organization', 'committee');

            // Link every CONTRIBUTION for this committee to the committee entity. Scoped to
            // contributions so expenditures/loans (which share this table) aren't treated as donations.
            $linked = FinanceTransaction::where('committee_name', $committeeName)
                ->where('data_type', 'contributions')
                ->update(['committee_entity_id' => $committee->id]);

            // 2. Aggregate the committee's donors, then keep those above the threshold. Filtering in
            //    PHP (the per-committee donor set is small) keeps the SQL simple and portable.
            $donors = FinanceTransaction::query()
                ->where('committee_name', $committeeName)
                ->where('data_type', 'contributions')
                ->whereNotNull('contributor_name')->where('contributor_name', '!=', '')
                ->selectRaw('contributor_name,
                    SUM(amount) as total, COUNT(*) as n,
                    MIN(year) as first_year, MAX(year) as last_year,
                    MAX(contributor_type) as ctype')
                ->groupBy('contributor_name')
                ->orderByDesc('total')
                ->get()
                ->filter(fn ($d): bool => (float) $d->total >= $minTotal)
                ->take($maxDonors)
                ->values();

            $connections = 0;

            foreach ($donors as $d) {
                $isIndividual = stripos((string) $d->ctype, 'individual') !== false;
                $donor = $this->findOrCreateEntity($d->contributor_name, $isIndividual ? 'person' : 'organization');

                // Link this donor's transactions to the committee to the donor entity + approve them.
                FinanceTransaction::where('committee_name', $committeeName)
                    ->where('data_type', 'contributions')
                    ->where('contributor_name', $d->contributor_name)
                    ->update([
                        'contributor_entity_id' => $donor->id,
                        'committee_entity_id' => $committee->id,
                        'match_state' => 'approved',
                    ]);

                // One aggregated donation edge per donor → committee.
                $years = $d->first_year === $d->last_year ? $d->first_year : "{$d->first_year}–{$d->last_year}";
                $note = sprintf(
                    'TRACER: $%s across %d contribution%s (%s). Import-sourced; attach filings to verify.',
                    number_format((float) $d->total, 2),
                    (int) $d->n,
                    $d->n == 1 ? '' : 's',
                    $years,
                );

                $rel = Relationship::firstOrNew([
                    'from_entity_id' => $donor->id,
                    'to_entity_id' => $committee->id,
                    'relationship_type_id' => $donatedTo?->id,
                ]);
                // Don't downgrade a human-verified edge back to reported.
                if (! $rel->exists) {
                    $rel->verification_state = 'reported';
                    $rel->status = 'active';
                    $rel->sensitivity = 'internal';
                }
                $rel->notes = $note;
                $rel->save();
                $connections++;
            }

            return [
                'committee_entity_id' => $committee->id,
                'donors_promoted' => $donors->count(),
                'connections' => $connections,
                'transactions_linked' => $linked,
            ];
        });
    }

    /**
     * Auto-build donation networks for EVERY committee whose total (from the imported data) clears
     * $minCommitteeTotal, promoting each committee's donors above $minDonorTotal. Idempotent, so it
     * can be re-run as new data arrives. Thresholds keep it to consequential money rather than
     * minting an entity for every one-time small donor.
     *
     * @return array{committees:int, donors_promoted:int, connections:int}
     */
    public function buildAllCommittees(float $minCommitteeTotal = self::DEFAULT_MIN_COMMITTEE_TOTAL, float $minDonorTotal = self::DEFAULT_MIN_DONOR_TOTAL, int $maxDonorsPerCommittee = self::DEFAULT_MAX_DONORS_PER_COMMITTEE): array
    {
        $committees = FinanceTransaction::query()
            ->where('data_type', 'contributions')
            ->whereNotNull('committee_name')->where('committee_name', '!=', '')
            ->selectRaw('committee_name, SUM(amount) as total')
            ->groupBy('committee_name')
            ->get()
            ->filter(fn ($c): bool => (float) $c->total >= $minCommitteeTotal)
            ->pluck('committee_name');

        return $this->buildForCommittees($committees, $minCommitteeTotal, $minDonorTotal, $maxDonorsPerCommittee);
    }

    /**
     * Build donor networks for a specific set of committee names (e.g. the committees a single import
     * batch touched) that clear the committee-total floor. Used to keep the graph current right after
     * an import, without re-scanning every committee in the dataset.
     *
     * @param  iterable<string>  $committeeNames
     * @return array{committees:int, donors_promoted:int, connections:int}
     */
    public function buildForCommittees(iterable $committeeNames, float $minCommitteeTotal = self::DEFAULT_MIN_COMMITTEE_TOTAL, float $minDonorTotal = self::DEFAULT_MIN_DONOR_TOTAL, int $maxDonorsPerCommittee = self::DEFAULT_MAX_DONORS_PER_COMMITTEE): array
    {
        $built = 0;
        $donors = 0;
        $connections = 0;

        foreach ($committeeNames as $name) {
            if ($name === null || $name === '') {
                continue;
            }

            $total = (float) FinanceTransaction::query()
                ->where('data_type', 'contributions')
                ->where('committee_name', $name)
                ->sum('amount');
            if ($total < $minCommitteeTotal) {
                continue;
            }

            $r = $this->buildFromCommittee($name, $minDonorTotal, $maxDonorsPerCommittee);
            $built++;
            $donors += $r['donors_promoted'];
            $connections += $r['connections'];
        }

        return ['committees' => $built, 'donors_promoted' => $donors, 'connections' => $connections];
    }

    /** Find an entity by display name / alias (case-insensitive), or create it. */
    private function findOrCreateEntity(string $name, string $type, ?string $orgSubtype = null): Entity
    {
        $existing = Entity::query()
            ->where(fn ($q) => $q->whereRaw('LOWER(display_name) = ?', [mb_strtolower($name)])
                ->orWhereHas('aliases', fn ($a) => $a->whereRaw('LOWER(alias) = ?', [mb_strtolower($name)])))
            ->first();

        if ($existing) {
            return $existing;
        }

        $entity = Entity::create([
            'entity_type' => $type,
            'origin' => 'finance_import', // keeps auto-created actors out of the curated dossier list
            'display_name' => $name,
            'sensitivity' => 'internal',
        ]);

        if ($type === 'organization' && $orgSubtype) {
            $entity->organizationProfile()->create(['org_subtype' => $orgSubtype]);
        }

        return $entity;
    }
}
