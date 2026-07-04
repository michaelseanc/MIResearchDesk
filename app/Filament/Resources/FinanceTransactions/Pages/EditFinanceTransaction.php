<?php

namespace App\Filament\Resources\FinanceTransactions\Pages;

use App\Filament\Resources\FinanceTransactions\FinanceTransactionResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditFinanceTransaction extends EditRecord
{
    protected static string $resource = FinanceTransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
