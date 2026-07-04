<?php

namespace App\Filament\Resources\Expenditures;

use App\Filament\Resources\Expenditures\Pages\ListExpenditures;
use App\Filament\Resources\Expenditures\Tables\ExpendituresTable;
use App\Models\FinanceTransaction;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * TRACER expenditures — money committees spent (payees/vendors). Same underlying table as
 * contributions/loans, scoped by data_type so each is browsable on its own.
 */
class ExpenditureResource extends Resource
{
    protected static ?string $model = FinanceTransaction::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static ?string $navigationLabel = 'Expenditures';

    protected static ?string $modelLabel = 'expenditure';

    protected static string|\UnitEnum|null $navigationGroup = 'Campaign finance';

    protected static ?int $navigationSort = 2;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('data_type', 'expenditures');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return ExpendituresTable::configure($table);
    }

    public static function getPages(): array
    {
        // Imported, not hand-created — view/filter only.
        return [
            'index' => ListExpenditures::route('/'),
        ];
    }
}
