<?php

namespace App\Filament\Resources\FinanceImportBatches\Pages;

use App\Filament\Resources\FinanceImportBatches\FinanceImportBatchResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditFinanceImportBatch extends EditRecord
{
    protected static string $resource = FinanceImportBatchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
