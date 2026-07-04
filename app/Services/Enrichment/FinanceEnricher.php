<?php

namespace App\Services\Enrichment;

use App\Models\Entity;
use App\Models\FinanceTransaction;
use Illuminate\Database\Eloquent\Builder;

/**
 * Tier 1 dossier enrichment: fill BLANK dossier fields from the TRACER data already linked to an
 * entity. Zero external calls, zero scraping — everything here is derived from public-record finance
 * data the newsroom already imported, so it's safe to store and trust. Non-destructive by default
 * (only fills blanks); pass $overwrite to refresh finance-derived fields.
 *
 * See docs/design/enrichment-architecture.md — this is the compliant, scalable core; external
 * enrichment (Wikidata, web search) is layered on top as Tiers 2–3 with human review.
 */
class FinanceEnricher
{
    /**
     * @return array<int, string> Human-readable labels of the fields that were filled.
     */
    public function enrich(Entity $entity, bool $overwrite = false): array
    {
        return $entity->entity_type === 'person'
            ? $this->enrichPerson($entity, $overwrite)
            : $this->enrichOrganization($entity, $overwrite);
    }

    /** @return array<int, string> */
    protected function enrichPerson(Entity $entity, bool $overwrite): array
    {
        $base = FinanceTransaction::query()
            ->where('data_type', 'contributions')
            ->where('contributor_entity_id', $entity->id);

        if ((clone $base)->doesntExist()) {
            return [];
        }

        $filled = [];
        $profile = $entity->personProfile()->firstOrCreate([]);

        if ($occupation = $this->mode($base, 'occupation')) {
            if ($this->set($profile, 'professional_role', $occupation, $overwrite)) {
                $filled[] = 'role/title';
            }
        }
        if ($employer = $this->mode($base, 'employer')) {
            if ($this->set($profile, 'current_company', $employer, $overwrite)) {
                $filled[] = 'employer';
            }
        }
        if ($geo = $this->modeGeography($base)) {
            if ($this->set($entity, 'primary_geography', $geo, $overwrite)) {
                $filled[] = 'geography';
            }
        }
        if ($summary = $this->givingSummary($base)) {
            if ($this->set($entity, 'internal_summary', $summary, $overwrite)) {
                $filled[] = 'internal summary';
            }
        }

        $profile->save();
        $entity->save();

        return $filled;
    }

    /** @return array<int, string> */
    protected function enrichOrganization(Entity $entity, bool $overwrite): array
    {
        $received = FinanceTransaction::query()
            ->where('data_type', 'contributions')
            ->where('committee_entity_id', $entity->id);
        $made = FinanceTransaction::query()
            ->where('data_type', 'contributions')
            ->where('contributor_entity_id', $entity->id);

        $summary = null;
        if ((clone $received)->exists()) {
            $summary = $this->receivedSummary($received);
        } elseif ((clone $made)->exists()) {
            $summary = $this->givingSummary($made);
        }

        if (! $summary) {
            return [];
        }

        $filled = [];
        if ($this->set($entity, 'internal_summary', $summary, $overwrite)) {
            $filled[] = 'internal summary';
        }
        $entity->save();

        return $filled;
    }

    /** Most frequent non-empty value of a column across the query. */
    protected function mode(Builder $base, string $column): ?string
    {
        return (clone $base)
            ->whereNotNull($column)->where($column, '!=', '')
            ->selectRaw("{$column} as v, COUNT(*) as c")
            ->groupBy($column)->orderByDesc('c')
            ->value('v');
    }

    /** Most frequent "City, ST" across the query. */
    protected function modeGeography(Builder $base): ?string
    {
        $row = (clone $base)
            ->whereNotNull('city')->where('city', '!=', '')
            ->selectRaw('city, state, COUNT(*) as c')
            ->groupBy('city', 'state')->orderByDesc('c')
            ->first();

        if (! $row) {
            return null;
        }

        return trim($row->city . ($row->state ? ', ' . $row->state : ''));
    }

    protected function givingSummary(Builder $base): ?string
    {
        $agg = (clone $base)->selectRaw('COUNT(*) n, SUM(amount) total, MIN(year) miny, MAX(year) maxy')->first();
        if (! $agg || (int) $agg->n === 0) {
            return null;
        }

        $top = (clone $base)->whereNotNull('committee_name')->where('committee_name', '!=', '')
            ->selectRaw('committee_name, SUM(amount) s')->groupBy('committee_name')
            ->orderByDesc('s')->limit(3)->pluck('committee_name')->all();

        return sprintf(
            'Finance-derived: gave %s across %d contribution%s (%s).%s',
            $this->money($agg->total),
            (int) $agg->n,
            (int) $agg->n === 1 ? '' : 's',
            $this->years($agg->miny, $agg->maxy),
            $top ? ' Top recipients: ' . implode('; ', $top) . '.' : '',
        );
    }

    protected function receivedSummary(Builder $base): ?string
    {
        $agg = (clone $base)->selectRaw('COUNT(*) n, SUM(amount) total, MIN(year) miny, MAX(year) maxy')->first();
        if (! $agg || (int) $agg->n === 0) {
            return null;
        }

        $top = (clone $base)->whereNotNull('contributor_name')->where('contributor_name', '!=', '')
            ->selectRaw('contributor_name, SUM(amount) s')->groupBy('contributor_name')
            ->orderByDesc('s')->limit(3)->pluck('contributor_name')->all();

        return sprintf(
            'Finance-derived: received %s across %d contribution%s (%s).%s',
            $this->money($agg->total),
            (int) $agg->n,
            (int) $agg->n === 1 ? '' : 's',
            $this->years($agg->miny, $agg->maxy),
            $top ? ' Top donors: ' . implode('; ', $top) . '.' : '',
        );
    }

    /** Set a field only when blank (or when overwriting). Returns true if it changed. */
    protected function set($model, string $attribute, string $value, bool $overwrite): bool
    {
        $current = $model->{$attribute};
        if (! $overwrite && $current !== null && trim((string) $current) !== '') {
            return false;
        }
        if ((string) $current === $value) {
            return false;
        }
        $model->{$attribute} = $value;

        return true;
    }

    protected function money($v): string
    {
        return '$' . number_format((float) $v, 0);
    }

    protected function years($min, $max): string
    {
        $min = (int) $min;
        $max = (int) $max;
        if ($min === 0 && $max === 0) {
            return 'year n/a';
        }

        return $min === $max ? (string) $min : "{$min}–{$max}";
    }
}
