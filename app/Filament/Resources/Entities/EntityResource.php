<?php

namespace App\Filament\Resources\Entities;

use App\Filament\Resources\Entities\Pages\CreateEntity;
use App\Filament\Resources\Entities\Pages\EditEntity;
use App\Filament\Resources\Entities\Pages\ListEntities;
use App\Filament\Resources\Entities\Schemas\EntityForm;
use App\Filament\Resources\Entities\Tables\EntitiesTable;
use App\Models\Entity;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class EntityResource extends Resource
{
    protected static ?string $model = Entity::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $navigationLabel = 'People & Organizations';

    protected static string|\UnitEnum|null $navigationGroup = 'Research';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'display_name';

    /**
     * Base query for lists/search. Sealed records are excluded unless the user is explicitly
     * permitted to view confidential identities — a contributor never even sees they exist.
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        if (! auth()->user()?->can('view_confidential_identity')) {
            $query->where('sensitivity', '!=', 'sealed');
        }

        return $query;
    }

    /** @return array<string> */
    public static function getGloballySearchableAttributes(): array
    {
        return ['display_name', 'legal_name'];
    }

    public static function form(Schema $schema): Schema
    {
        return EntityForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EntitiesTable::configure($table);
    }

    public static function getRelations(): array
    {
        // Contact methods now live at the top of the form; the panels below are the
        // reference/working areas of the dossier.
        return [
            RelationManagers\RelationshipsFromRelationManager::class,
            RelationManagers\AddressesRelationManager::class,
            RelationManagers\LinksRelationManager::class,
            RelationManagers\InteractionsRelationManager::class,
            RelationManagers\DocumentsRelationManager::class,
            RelationManagers\DonationsMadeRelationManager::class,
            RelationManagers\DonationsReceivedRelationManager::class,
            RelationManagers\EmployeeGivingRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEntities::route('/'),
            'create' => CreateEntity::route('/create'),
            'edit' => EditEntity::route('/{record}/edit'),
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
