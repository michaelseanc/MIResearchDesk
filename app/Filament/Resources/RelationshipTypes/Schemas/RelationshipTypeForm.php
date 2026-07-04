<?php

namespace App\Filament\Resources\RelationshipTypes\Schemas;

use App\Models\RelationshipType;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class RelationshipTypeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('label')->label('Display name')->required()->maxLength(255)
                ->helperText('How it reads on a connection, e.g. “Registered agent for”.'),
            TextInput::make('inverse_name')->label('Reverse label')->maxLength(255)
                ->helperText('How it reads from the other side (shown on incoming connections).'),
            Toggle::make('is_directional')->label('Directional (A → B)')->default(true),
            Select::make('category')
                ->options(RelationshipType::CATEGORY_OPTIONS)
                ->native(false),
            Select::make('color')
                ->label('Badge color')
                ->options(RelationshipType::COLOR_OPTIONS)
                ->native(false)
                ->placeholder('Default (by category)'),
        ]);
    }
}
