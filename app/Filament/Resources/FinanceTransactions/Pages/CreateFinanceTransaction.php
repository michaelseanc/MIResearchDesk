<?php

namespace App\Filament\Resources\FinanceTransactions\Pages;

use App\Filament\Resources\FinanceTransactions\FinanceTransactionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateFinanceTransaction extends CreateRecord
{
    protected static string $resource = FinanceTransactionResource::class;
}
