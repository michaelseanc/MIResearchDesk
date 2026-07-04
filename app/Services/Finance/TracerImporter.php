<?php

namespace App\Services\Finance;

use App\Models\Entity;
use App\Models\FinanceImportBatch;
use App\Models\FinanceTransaction;
use App\Models\Organization;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Orchestrates a TRACER pull: download → extract → stream-parse the rows → filter to what the
 * newsroom tracks → dedupe → auto-match committees/candidates to entities. Raw source fields are
 * preserved verbatim; entity links are the newsroom's separate, reviewable interpretation.
 *
 * ONE generic parser handles all three TRACER datasets — contributions, expenditures, and loans —
 * because mapping is BY COLUMN NAME from each file's header row (with per-dataset header aliases,
 * first match wins), not by fixed position. Confirmed against the real bulk-file headers:
 *   Contributions: ContributionAmount / ContributionDate / ContributionType, payee split LastName…
 *   Expenditures:  ExpenditureAmount  / ExpenditureDate  / ExpenditureType,  payee split LastName…
 *   Loans:         LoanAmount / LoanDate / LoanSourceType, lender in a single "Name" column
 */
class TracerImporter
{
    /** our field => candidate source header names, across all three datasets (first match wins). */
    private const FIELD_MAP = [
        'file_number' => ['RecordID'],
        'committee_type' => ['CommitteeType'],
        'committee_name' => ['CommitteeName'],
        'candidate_name' => ['CandidateName'],
        'contributor_type' => ['ContributorType'],
        'address' => ['Address1'],
        'city' => ['City'],
        'state' => ['State'],
        'jurisdiction' => ['Jurisdiction'],
        'zip' => ['Zip'],
        'occupation' => ['Occupation'],
        'employer' => ['Employer'],
        'txn_subtype' => ['ContributionType', 'ExpenditureType', 'LoanSourceType', 'Type'],
        'description' => ['Explanation', 'Description'],
        'amount' => ['ContributionAmount', 'ExpenditureAmount', 'LoanAmount', 'PaymentAmount'],
        'transaction_date' => ['ContributionDate', 'ExpenditureDate', 'LoanDate', 'PaymentDate'],
        'received_by' => ['ReceiptType', 'PaymentType'],
        'amended' => ['Amended'],
    ];

    /**
     * Colorado's 64 counties in TRACER's exact (uppercase) "Jurisdiction" spelling. Used to offer a
     * county picker at import and to match rows. Non-local races carry STATEWIDE / FEDERAL instead.
     */
    public const COUNTIES = [
        'ADAMS', 'ALAMOSA', 'ARAPAHOE', 'ARCHULETA', 'BACA', 'BENT', 'BOULDER', 'BROOMFIELD',
        'CHAFFEE', 'CHEYENNE', 'CLEAR CREEK', 'CONEJOS', 'COSTILLA', 'CROWLEY', 'CUSTER', 'DELTA',
        'DENVER', 'DOLORES', 'DOUGLAS', 'EAGLE', 'ELBERT', 'EL PASO', 'FREMONT', 'GARFIELD',
        'GILPIN', 'GRAND', 'GUNNISON', 'HINSDALE', 'HUERFANO', 'JACKSON', 'JEFFERSON', 'KIOWA',
        'KIT CARSON', 'LA PLATA', 'LAKE', 'LARIMER', 'LAS ANIMAS', 'LINCOLN', 'LOGAN', 'MESA',
        'MINERAL', 'MOFFAT', 'MONTEZUMA', 'MONTROSE', 'MORGAN', 'OTERO', 'OURAY', 'PARK',
        'PHILLIPS', 'PITKIN', 'PROWERS', 'PUEBLO', 'RIO BLANCO', 'RIO GRANDE', 'ROUTT', 'SAGUACHE',
        'SAN JUAN', 'SAN MIGUEL', 'SEDGWICK', 'SUMMIT', 'TELLER', 'WASHINGTON', 'WELD', 'YUMA',
    ];

    /** dataset-specific source columns preserved verbatim in source_extra (our field => header). */
    private const EXTRA_MAP = [
        'loan_balance' => 'LoanBalance',
        'interest_rate' => 'InterestRate',
        'interest_payment' => 'InterestPayment',
        'disbursement_type' => 'DisbursementType',
    ];

    public function __construct(private TracerClient $client = new TracerClient()) {}

    /** Full pipeline for a batch (used by the queued job and the console command). */
    public function run(FinanceImportBatch $batch): void
    {
        Organization::useOrganization($batch->organization_id);
        $csvPath = null;

        try {
            $batch->update(['status' => 'downloading', 'started_at' => now()]);
            $csvPath = $this->client->fetchCsv($batch);

            $batch->update(['status' => 'parsing']);
            $this->parseRows($csvPath, $batch);
            $this->autoMatch($batch);
            $this->buildNetworks($batch);

            $batch->update(['status' => 'completed', 'completed_at' => now()]);
        } catch (Throwable $e) {
            $batch->update(['status' => 'failed', 'error' => Str::limit($e->getMessage(), 2000)]);
            throw $e;
        } finally {
            // Tidy temp extraction (the untouched original is preserved on the private disk).
            if ($csvPath && is_file($csvPath)) {
                @unlink($csvPath);
                @rmdir(dirname($csvPath));
            }
        }
    }

    /**
     * Stream a TRACER CSV (contributions, expenditures, or loans) into finance_transactions. Public
     * + path-based so tests can feed a fixture directly, bypassing the network download.
     */
    public function parseRows(string $csvPath, FinanceImportBatch $batch): void
    {
        Organization::useOrganization($batch->organization_id);
        $filter = $batch->filter ?? [];

        $handle = fopen($csvPath, 'r');
        if ($handle === false) {
            throw new \RuntimeException("Could not open CSV: {$csvPath}");
        }

        $header = fgetcsv($handle, 0, ',', '"', '');
        if (! is_array($header)) {
            fclose($handle);
            throw new \RuntimeException('TRACER CSV had no header row.');
        }
        $index = $this->headerIndex($header);

        $total = $imported = $skipped = 0;

        // One transaction per file — a large speed win for SQLite bulk inserts.
        DB::beginTransaction();
        try {
            while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
                if ($row === [null]) {
                    continue; // blank line
                }
                $total++;

                $data = $this->mapRow($row, $index);
                if (! $this->passesFilter($data, $filter)) {
                    $skipped++;
                    continue;
                }

                $hash = $this->rowHash($batch, $data);

                FinanceTransaction::updateOrCreate(
                    ['organization_id' => $batch->organization_id, 'row_hash' => $hash],
                    array_merge($data, [
                        'import_batch_id' => $batch->id,
                        'source' => $batch->source,
                        'data_type' => $batch->data_type,
                        'year' => $batch->year,
                    ]),
                );
                $imported++;
            }
            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            fclose($handle);
            throw $e;
        }

        fclose($handle);

        $batch->update([
            'rows_total' => $total,
            'rows_imported' => $imported,
            'rows_skipped' => $skipped,
        ]);
    }

    /** Build a case-insensitive header-name => column-position map. */
    private function headerIndex(array $header): array
    {
        $index = [];
        foreach ($header as $pos => $name) {
            $index[strtolower(trim((string) $name))] = $pos;
        }

        return $index;
    }

    /** Map a raw CSV row to normalized fields using the header index. */
    private function mapRow(array $row, array $index): array
    {
        $col = function (string $headerName) use ($row, $index): ?string {
            $pos = $index[strtolower($headerName)] ?? null;
            if ($pos === null || ! isset($row[$pos])) {
                return null;
            }
            $val = trim((string) $row[$pos]);

            return $val === '' ? null : $val;
        };

        $data = [];
        foreach (self::FIELD_MAP as $field => $candidates) {
            $value = null;
            foreach ($candidates as $headerName) {
                $value = $col($headerName);
                if ($value !== null) {
                    break;
                }
            }
            $data[$field] = $value;
        }

        // Contributor/payee name: contributions & expenditures split it across parts (org names sit
        // wholly in LastName); loans put the lender in a single "Name" column. Try parts, then Name.
        $data['contributor_name'] = $this->composeName(
            $col('FirstName'),
            $col('MI'),
            $col('LastName'),
            $col('Suffix'),
        ) ?? $col('Name');

        // Append the secondary address line (suite/unit) when present.
        $address2 = $col('Address2');
        if ($address2) {
            $data['address'] = $data['address'] ? $data['address'] . ', ' . $address2 : $address2;
        }

        $data['amount'] = $this->parseAmount($data['amount'] ?? null);
        $data['transaction_date'] = $this->parseDate($data['transaction_date'] ?? null);
        $data['zip'] = $data['zip'] ? substr($data['zip'], 0, 20) : null;

        // Preserve dataset-specific columns (e.g. a loan's balance/interest) verbatim.
        $extra = [];
        foreach (self::EXTRA_MAP as $field => $headerName) {
            $value = $col($headerName);
            if ($value !== null) {
                $extra[$field] = $value;
            }
        }
        $data['source_extra'] = $extra ?: null;

        return $data;
    }

    private function composeName(?string $first, ?string $mi, ?string $last, ?string $suffix): ?string
    {
        $name = trim(preg_replace('/\s+/', ' ', implode(' ', array_filter([$first, $mi, $last, $suffix]))));

        return $name === '' ? null : $name;
    }

    private function parseAmount(?string $raw): ?float
    {
        if ($raw === null) {
            return null;
        }
        $clean = preg_replace('/[^0-9.\-]/', '', $raw);

        return $clean === '' || $clean === '-' ? null : (float) $clean;
    }

    private function parseDate(?string $raw): ?string
    {
        if (! $raw) {
            return null;
        }
        try {
            return Carbon::parse($raw)->toDateString();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Keep a row if no filter is set, or if it matches any provided facet (tracked committee/
     * candidate/contributor name terms, counties, cities, or ZIPs). This keeps a local newsroom's
     * DB lean while still pulling from the full state file. Facets are OR'd together.
     */
    private function passesFilter(array $data, array $filter): bool
    {
        $terms = array_filter($filter['terms'] ?? []);
        $counties = array_map('strtoupper', array_filter($filter['counties'] ?? []));
        $cities = array_map('strtolower', array_filter($filter['cities'] ?? []));
        $zips = array_filter($filter['zips'] ?? []);

        if (! $terms && ! $counties && ! $cities && ! $zips) {
            return true; // no filter → import everything
        }

        $haystack = strtolower(implode(' ', array_filter([
            $data['committee_name'] ?? null,
            $data['candidate_name'] ?? null,
            $data['contributor_name'] ?? null,
        ])));

        foreach ($terms as $term) {
            if ($term !== '' && str_contains($haystack, strtolower($term))) {
                return true;
            }
        }
        if ($counties && in_array(strtoupper((string) ($data['jurisdiction'] ?? '')), $counties, true)) {
            return true;
        }
        if ($cities && in_array(strtolower((string) ($data['city'] ?? '')), $cities, true)) {
            return true;
        }
        if ($zips && in_array((string) ($data['zip'] ?? ''), $zips, true)) {
            return true;
        }

        return false;
    }

    private function rowHash(FinanceImportBatch $batch, array $data): string
    {
        return hash('sha256', implode('|', [
            $batch->data_type,
            $data['file_number'] ?? '',
            $data['committee_name'] ?? '',
            $data['contributor_name'] ?? '',
            $data['amount'] ?? '',
            $data['transaction_date'] ?? '',
        ]));
    }

    /**
     * Exact-name auto-match of committee/candidate/contributor to existing entities (by display
     * name or alias). Conservative: only sets match_state=auto when the committee or candidate —
     * the consequential links — resolve. Everything else stays 'unmatched' for human review.
     */
    public function autoMatch(FinanceImportBatch $batch): void
    {
        Organization::useOrganization($batch->organization_id);

        $lookup = [];
        $resolve = function (?string $name) use (&$lookup): ?int {
            if (! $name) {
                return null;
            }
            $key = strtolower($name);
            if (array_key_exists($key, $lookup)) {
                return $lookup[$key];
            }

            $id = Entity::query()
                ->where(fn ($q) => $q->whereRaw('LOWER(display_name) = ?', [$key])
                    ->orWhereHas('aliases', fn ($a) => $a->whereRaw('LOWER(alias) = ?', [$key])))
                ->value('id');

            return $lookup[$key] = $id ? (int) $id : null;
        };

        FinanceTransaction::where('import_batch_id', $batch->id)
            ->where('match_state', 'unmatched')
            ->chunkById(500, function ($txns) use ($resolve): void {
                foreach ($txns as $txn) {
                    $committee = $resolve($txn->committee_name);
                    $candidate = $resolve($txn->candidate_name);
                    $contributor = $resolve($txn->contributor_name);

                    $txn->committee_entity_id = $committee;
                    $txn->candidate_entity_id = $candidate;
                    $txn->contributor_entity_id = $contributor;
                    $txn->match_state = ($committee || $candidate) ? 'auto' : 'unmatched';
                    $txn->save();
                }
            });
    }

    /**
     * After a contributions import, materialize donor networks for the committees this batch touched
     * (that clear the committee-total floor) so the graph reflects the new money without a manual
     * rebuild. Non-fatal: the contributions are already parsed + committed, so a build hiccup is
     * logged rather than failing the whole import. Only contributions form donation edges.
     */
    public function buildNetworks(FinanceImportBatch $batch): void
    {
        if ($batch->data_type !== 'contributions') {
            return;
        }

        try {
            $names = FinanceTransaction::where('import_batch_id', $batch->id)
                ->where('data_type', 'contributions')
                ->whereNotNull('committee_name')->where('committee_name', '!=', '')
                ->distinct()->pluck('committee_name');

            $cfg = Organization::networkConfig($batch->organization_id);

            $result = app(FinanceNetworkBuilder::class)->buildForCommittees(
                $names,
                $cfg['min_committee_total'],
                $cfg['min_donor_total'],
                $cfg['max_donors_per_committee'],
            );

            $batch->update([
                'network_committees' => $result['committees'],
                'network_connections' => $result['connections'],
            ]);
        } catch (Throwable $e) {
            // Import succeeded; don't fail it because the (re-runnable) network build stumbled.
            Log::warning("Finance network build failed for batch {$batch->id}: {$e->getMessage()}");
        }
    }

    /** Create a pending batch for a given dataset/year/filter. */
    public static function createBatch(int $organizationId, string $dataType, int $year, array $filter = []): FinanceImportBatch
    {
        Organization::useOrganization($organizationId);

        return FinanceImportBatch::create([
            'source' => 'tracer',
            'data_type' => $dataType,
            'year' => $year,
            'filter' => $filter ?: null,
            'status' => 'pending',
            'created_by' => auth()->id(),
        ]);
    }
}
