<?php

namespace App\Filament\Resources\Entities\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Phone / email / Signal / social handles for an entity. Newsroom-specific fields (preferred
 * channel, contact restrictions like "source-safe channel" or "do not call") live here rather
 * than as flat columns, because how you may contact a source is itself sensitive reporting data.
 */
class ContactMethodsRelationManager extends RelationManager
{
    protected static string $relationship = 'contactMethods';

    protected static ?string $title = 'Contact methods';

    protected static string|\BackedEnum|null $icon = 'heroicon-o-phone';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('method')
                ->options([
                    'phone' => 'Phone',
                    'email' => 'Email',
                    'signal' => 'Signal',
                    'social' => 'Social account',
                    'in_person' => 'In person',
                ])
                ->required()->native(false),
            TextInput::make('value')
                ->label('Value')
                ->helperText('Number, address, handle, or URL.')
                ->required()->maxLength(255),
            Toggle::make('is_preferred')->label('Preferred channel'),
            Select::make('restrictions')
                ->options([
                    'do_not_call' => 'Do not call',
                    'text_only' => 'Text only',
                    'no_voicemail' => 'No voicemail',
                    'source_safe' => 'Source-safe channel',
                ])
                ->native(false)->placeholder('None'),
            Select::make('sensitivity')
                ->options([
                    'public' => 'Public',
                    'internal' => 'Internal',
                    'confidential' => 'Confidential',
                ])->default('internal')->required(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('value')
            ->columns([
                TextColumn::make('method')->badge()->formatStateUsing(fn (string $state): string => ucfirst(str_replace('_', ' ', $state))),
                TextColumn::make('value')->searchable()->copyable(),
                IconColumn::make('is_preferred')->label('Preferred')->boolean(),
                TextColumn::make('restrictions')->badge()->color('warning')->placeholder('—')
                    ->formatStateUsing(fn (?string $state): string => $state ? ucfirst(str_replace('_', ' ', $state)) : '—'),
                TextColumn::make('sensitivity')->badge()->toggleable(),
            ])
            ->headerActions([
                CreateAction::make()->label('Add contact method'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
