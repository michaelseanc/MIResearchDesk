<?php

namespace App\Filament\Resources\FinanceImportBatches\Widgets;

use App\Models\FinanceImportBatch;
use Filament\Widgets\Widget;

/**
 * Flags imports that appear genuinely stuck — queued for a while with NOTHING actively processing.
 * Stays quiet while an import is downloading/parsing (a worker is clearly running and others are
 * just queued behind it), so it doesn't cry wolf during normal multi-import runs.
 */
class PendingImportsWarning extends Widget
{
    protected string $view = 'filament.resources.finance-import-batches.widgets.pending-imports-warning';

    protected int|string|array $columnSpan = 'full';

    protected static ?string $pollingInterval = '15s';

    /** Pending batches waiting >3 min with no batch in progress → the worker likely isn't running. */
    public function getStalled(): int
    {
        // A worker is actively churning → pending batches are just queued behind it, not stuck.
        if (FinanceImportBatch::query()->whereIn('status', ['downloading', 'parsing'])->exists()) {
            return 0;
        }

        return FinanceImportBatch::query()
            ->where('status', 'pending')
            ->where('created_at', '<', now()->subMinutes(3))
            ->count();
    }
}
