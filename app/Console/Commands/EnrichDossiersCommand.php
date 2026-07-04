<?php

namespace App\Console\Commands;

use App\Models\Entity;
use App\Models\FinanceTransaction;
use App\Models\Organization;
use App\Services\Enrichment\FinanceEnricher;
use Illuminate\Console\Command;

/**
 * Tier 1 mass enrichment: fill blank dossier fields from linked TRACER data, across every entity
 * that has finance records. Idempotent and non-destructive by default. Run after network builds.
 */
class EnrichDossiersCommand extends Command
{
    protected $signature = 'dossiers:enrich-from-finance
        {--org= : organization id (default: every organization)}
        {--overwrite : refresh finance-derived fields even if already filled}';

    protected $description = 'Fill blank dossier fields (role, employer, geography, summary) from linked TRACER data.';

    public function handle(FinanceEnricher $enricher): int
    {
        $overwrite = (bool) $this->option('overwrite');

        $orgIds = $this->option('org') !== null
            ? [(int) $this->option('org')]
            : Organization::query()->pluck('id')->all();

        $totalEntities = 0;
        $totalFilled = 0;

        foreach ($orgIds as $orgId) {
            Organization::useOrganization($orgId);

            // Entities that actually have finance data linked (as contributor or committee).
            $ids = FinanceTransaction::query()
                ->where('data_type', 'contributions')
                ->selectRaw('contributor_entity_id as id')->whereNotNull('contributor_entity_id')
                ->union(
                    FinanceTransaction::query()
                        ->where('data_type', 'contributions')
                        ->selectRaw('committee_entity_id as id')->whereNotNull('committee_entity_id')
                )
                ->pluck('id')->unique()->values();

            if ($ids->isEmpty()) {
                continue;
            }

            $this->info("Org {$orgId}: enriching {$ids->count()} entities with finance data…");
            $bar = $this->output->createProgressBar($ids->count());

            Entity::query()->whereIn('id', $ids)->chunkById(200, function ($entities) use ($enricher, $overwrite, &$totalEntities, &$totalFilled, $bar): void {
                foreach ($entities as $entity) {
                    $filled = $enricher->enrich($entity, $overwrite);
                    $totalEntities++;
                    $totalFilled += count($filled);
                    $bar->advance();
                }
            });

            $bar->finish();
            $this->newLine();
        }

        $this->info("✓ Processed {$totalEntities} dossiers; filled {$totalFilled} field(s).");

        return self::SUCCESS;
    }
}
