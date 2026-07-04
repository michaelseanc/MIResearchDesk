<?php

namespace App\Filament\Resources\RelationshipTypes;

use App\Filament\Resources\RelationshipTypes\Pages\CreateRelationshipType;
use App\Filament\Resources\RelationshipTypes\Pages\EditRelationshipType;
use App\Filament\Resources\RelationshipTypes\Pages\ListRelationshipTypes;
use App\Filament\Resources\RelationshipTypes\Schemas\RelationshipTypeForm;
use App\Filament\Resources\RelationshipTypes\Tables\RelationshipTypesTable;
use App\Models\RelationshipType;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class RelationshipTypeResource extends Resource
{
    protected static ?string $model = RelationshipType::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShare;

    protected static ?string $navigationLabel = 'Connection types';

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $recordTitleAttribute = 'label';

    public static function form(Schema $schema): Schema
    {
        return RelationshipTypeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RelationshipTypesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRelationshipTypes::route('/'),
            'create' => CreateRelationshipType::route('/create'),
            'edit' => EditRelationshipType::route('/{record}/edit'),
        ];
    }
}
