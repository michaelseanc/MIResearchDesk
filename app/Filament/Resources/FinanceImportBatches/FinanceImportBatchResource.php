<?php

namespace App\Filament\Resources\FinanceImportBatches;

use App\Filament\Resources\FinanceImportBatches\Pages\CreateFinanceImportBatch;
use App\Filament\Resources\FinanceImportBatches\Pages\EditFinanceImportBatch;
use App\Filament\Resources\FinanceImportBatches\Pages\ListFinanceImportBatches;
use App\Filament\Resources\FinanceImportBatches\Schemas\FinanceImportBatchForm;
use App\Filament\Resources\FinanceImportBatches\Tables\FinanceImportBatchesTable;
use App\Models\FinanceImportBatch;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class FinanceImportBatchResource extends Resource
{
    protected static ?string $model = FinanceImportBatch::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowDownTray;

    protected static ?string $navigationLabel = 'TRACER imports';

    protected static string|\UnitEnum|null $navigationGroup = 'Campaign finance';

    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return FinanceImportBatchForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FinanceImportBatchesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        // Batches are system-created by the import action, not hand-authored.
        return [
            'index' => ListFinanceImportBatches::route('/'),
        ];
    }
}
