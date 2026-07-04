<?php

namespace App\Filament\Resources\Relationships;

use App\Filament\Resources\Relationships\Pages\CreateRelationship;
use App\Filament\Resources\Relationships\Pages\EditRelationship;
use App\Filament\Resources\Relationships\Pages\ListRelationships;
use App\Filament\Resources\Relationships\Schemas\RelationshipForm;
use App\Filament\Resources\Relationships\Tables\RelationshipsTable;
use App\Models\Relationship;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class RelationshipResource extends Resource
{
    protected static ?string $model = Relationship::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShare;

    protected static ?string $navigationLabel = 'Connections';

    protected static string|\UnitEnum|null $navigationGroup = 'Research';

    protected static ?int $navigationSort = 3;

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        if (! auth()->user()?->can('view_confidential_identity')) {
            $query->where('sensitivity', '!=', 'sealed');
        }

        return $query;
    }

    public static function form(Schema $schema): Schema
    {
        return RelationshipForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RelationshipsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\EvidenceRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRelationships::route('/'),
            'create' => CreateRelationship::route('/create'),
            'edit' => EditRelationship::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
