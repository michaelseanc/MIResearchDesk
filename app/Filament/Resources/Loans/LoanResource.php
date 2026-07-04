<?php

namespace App\Filament\Resources\Loans;

use App\Filament\Resources\Loans\Pages\ListLoans;
use App\Filament\Resources\Loans\Tables\LoansTable;
use App\Models\FinanceTransaction;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * TRACER loans — money loaned to committees (lender, principal, outstanding balance, rate). Same
 * underlying table as contributions/expenditures, scoped by data_type.
 */
class LoanResource extends Resource
{
    protected static ?string $model = FinanceTransaction::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedScale;

    protected static ?string $navigationLabel = 'Loans';

    protected static ?string $modelLabel = 'loan';

    protected static string|\UnitEnum|null $navigationGroup = 'Campaign finance';

    protected static ?int $navigationSort = 3;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('data_type', 'loans');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return LoansTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLoans::route('/'),
        ];
    }
}
