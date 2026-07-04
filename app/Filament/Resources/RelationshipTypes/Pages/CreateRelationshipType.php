<?php

namespace App\Filament\Resources\RelationshipTypes\Pages;

use App\Filament\Resources\RelationshipTypes\RelationshipTypeResource;
use App\Models\RelationshipType;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreateRelationshipType extends CreateRecord
{
    protected static string $resource = RelationshipTypeResource::class;

    /** Derive a unique machine name from the label (the form only asks for the display name). */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $base = Str::slug($data['label'] ?? 'type', '_') ?: 'type';
        $name = $base;
        $i = 2;
        while (RelationshipType::where('name', $name)->exists()) {
            $name = "{$base}_{$i}";
            $i++;
        }
        $data['name'] = $name;

        return $data;
    }
}
