<?php

namespace App\Jobs;

use App\Models\FinanceImportBatch;
use App\Services\Finance\TracerImporter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Runs a TRACER import batch off the queue (the full state file can take a while to download and
 * parse). Loads the batch without the tenant scope — the queue has no authenticated user — then
 * the importer re-establishes the batch's organization context.
 */
class ImportTracerData implements ShouldQueue
{
    use Queueable;

    public int $timeout = 1800;

    public function __construct(public int $batchId) {}

    public function handle(TracerImporter $importer): void
    {
        $batch = FinanceImportBatch::withoutGlobalScopes()->find($this->batchId);

        if ($batch) {
            $importer->run($batch);
        }
    }
}
