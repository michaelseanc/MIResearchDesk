<?php

namespace App\Filament\Resources\RelationshipTypes\Pages;

use App\Filament\Resources\RelationshipTypes\RelationshipTypeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRelationshipTypes extends ListRecords
{
    protected static string $resource = RelationshipTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
