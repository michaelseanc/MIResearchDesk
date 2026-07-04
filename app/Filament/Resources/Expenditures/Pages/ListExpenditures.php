<?php

namespace App\Filament\Resources\Expenditures\Pages;

use App\Filament\Resources\Expenditures\ExpenditureResource;
use Filament\Resources\Pages\ListRecords;

class ListExpenditures extends ListRecords
{
    protected static string $resource = ExpenditureResource::class;
}
