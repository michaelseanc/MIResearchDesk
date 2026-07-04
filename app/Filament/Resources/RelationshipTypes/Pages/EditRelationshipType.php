<?php

namespace App\Filament\Resources\RelationshipTypes\Pages;

use App\Filament\Resources\RelationshipTypes\RelationshipTypeResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditRelationshipType extends EditRecord
{
    protected static string $resource = RelationshipTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
