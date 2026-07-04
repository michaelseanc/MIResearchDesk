<?php

namespace App\Filament\Resources\Tags\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class TagForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required()->maxLength(255),
            Select::make('kind')
                ->options([
                    'issue' => 'Issue',
                    'geography' => 'Geography',
                    'campaign' => 'Campaign',
                    'project' => 'Project',
                ])
                ->default('issue')
                ->required()
                ->helperText('“Issue” tags are what appear as Issue Context on connections, positions, and stories.'),
        ]);
    }
}
