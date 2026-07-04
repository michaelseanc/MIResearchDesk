<?php

namespace App\Filament\Resources\FinanceTransactions;

use App\Filament\Resources\FinanceTransactions\Pages\CreateFinanceTransaction;
use App\Filament\Resources\FinanceTransactions\Pages\EditFinanceTransaction;
use App\Filament\Resources\FinanceTransactions\Pages\ListFinanceTransactions;
use App\Filament\Resources\FinanceTransactions\Schemas\FinanceTransactionForm;
use App\Filament\Resources\FinanceTransactions\Tables\FinanceTransactionsTable;
use App\Models\FinanceTransaction;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class FinanceTransactionResource extends Resource
{
    protected static ?string $model = FinanceTransaction::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?string $navigationLabel = 'Contributions';

    protected static string|\UnitEnum|null $navigationGroup = 'Campaign finance';

    protected static ?int $navigationSort = 1;

    /** This resource is the Contributions view; Expenditures and Loans have their own resources. */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('data_type', 'contributions');
    }

    public static function form(Schema $schema): Schema
    {
        return FinanceTransactionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FinanceTransactionsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        // Transactions come from imports; they're explored & resolved, not hand-created.
        return [
            'index' => ListFinanceTransactions::route('/'),
        ];
    }
}
