<?php

namespace App\Filament\Resources\FinanceImportBatches\Widgets;

use App\Models\FinanceImportBatch;
use Filament\Widgets\Widget;

/**
 * Flags imports that were queued but never picked up by a background worker — the usual cause of a
 * "why is my import empty?" moment in local dev, where no persistent queue worker is running.
 * (In production on Cloudways a Supervisor-managed worker runs continuously, so this stays hidden.)
 */
class PendingImportsWarning extends Widget
{
    protected string $view = 'filament.resources.finance-import-batches.widgets.pending-imports-warning';

    protected int|string|array $columnSpan = 'full';

    protected static ?string $pollingInterval = '15s';

    /** Batches queued but not yet started for >30s → almost certainly no worker is processing them. */
    public function getStalled(): int
    {
        return FinanceImportBatch::query()
            ->where('status', 'pending')
            ->where('created_at', '<', now()->subSeconds(30))
            ->count();
    }
}
