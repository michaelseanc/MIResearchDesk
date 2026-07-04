<?php

namespace App\Console\Commands;

use App\Models\Organization;
use App\Services\Finance\TracerImporter;
use Illuminate\Console\Command;

/**
 * Pulls TRACER bulk data directly. Run for one organization with explicit filters, or `--all` to
 * refresh every active tenant using its saved finance filter (organizations.settings.finance_filter).
 * The weekly scheduler calls the `--all` form.
 */
class ImportTracerCommand extends Command
{
    protected $signature = 'finance:import-tracer
        {--type=contributions : contributions|expenditures|loans}
        {--year= : defaults to current year}
        {--org= : organization id (single-org mode)}
        {--terms= : comma-separated committee/candidate/contributor name terms to keep}
        {--cities= : comma-separated cities to keep}
        {--zips= : comma-separated ZIPs to keep}
        {--all : import for every active organization using its saved filter}';

    protected $description = 'Download and import Colorado TRACER campaign-finance data.';

    public function handle(TracerImporter $importer): int
    {
        $type = (string) $this->option('type');
        $year = (int) ($this->option('year') ?: now()->year);

        $targets = $this->option('all')
            ? Organization::where('status', 'active')->get()
                ->map(fn (Organization $o) => [$o->id, $this->savedFilter($o)])->all()
            : [[(int) ($this->option('org') ?: 1), $this->cliFilter()]];

        foreach ($targets as [$orgId, $filter]) {
            $this->info("Importing TRACER {$type} {$year} for org #{$orgId}…");
            $batch = TracerImporter::createBatch($orgId, $type, $year, $filter);

            try {
                $importer->run($batch);
                $this->info("  ✓ batch #{$batch->id}: {$batch->rows_imported} imported, {$batch->rows_skipped} skipped of {$batch->rows_total}.");
            } catch (\Throwable $e) {
                $this->error("  ✗ batch #{$batch->id} failed: {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }

    /** @return array{terms:array,cities:array,zips:array} */
    private function cliFilter(): array
    {
        $split = fn (?string $v): array => $v ? array_values(array_filter(array_map('trim', explode(',', $v)))) : [];

        return [
            'terms' => $split($this->option('terms')),
            'cities' => $split($this->option('cities')),
            'zips' => $split($this->option('zips')),
        ];
    }

    private function savedFilter(Organization $org): array
    {
        $f = $org->settings['finance_filter'] ?? [];

        return [
            'terms' => $f['terms'] ?? [],
            'cities' => $f['cities'] ?? [],
            'zips' => $f['zips'] ?? [],
        ];
    }
}
