<?php

namespace App\Filament\Resources\Entities\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Websites, social accounts, and pasted article links for an entity.
 */
class LinksRelationManager extends RelationManager
{
    protected static string $relationship = 'links';

    protected static ?string $title = 'Web & article links';

    protected static string|\BackedEnum|null $icon = 'heroicon-o-link';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('kind')
                ->options([
                    'website' => 'Website',
                    'social' => 'Social account',
                    'article' => 'Article link',
                    'other' => 'Other',
                ])
                ->default('website')->required()->live()->native(false),
            Select::make('platform')
                ->options([
                    'x' => 'X / Twitter',
                    'facebook' => 'Facebook',
                    'linkedin' => 'LinkedIn',
                    'instagram' => 'Instagram',
                    'youtube' => 'YouTube',
                    'tiktok' => 'TikTok',
                    'other' => 'Other',
                ])
                ->native(false)
                ->visible(fn (Get $get): bool => $get('kind') === 'social'),
            TextInput::make('url')
                ->label('URL')
                ->url()->required()->maxLength(1024)
                ->placeholder('https://…')->columnSpanFull(),
            TextInput::make('title')
                ->label(fn (Get $get): string => $get('kind') === 'article' ? 'Headline' : 'Label')
                ->maxLength(255),
            DatePicker::make('published_at')
                ->label('Published')
                ->visible(fn (Get $get): bool => $get('kind') === 'article'),
            Textarea::make('note')->rows(2)->columnSpanFull(),
            Select::make('sensitivity')
                ->options([
                    'public' => 'Public',
                    'internal' => 'Internal',
                    'confidential' => 'Confidential',
                ])->default('public')->required(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('url')
            ->columns([
                TextColumn::make('kind')->badge(),
                TextColumn::make('title')->searchable()->placeholder('—')->wrap(),
                TextColumn::make('url')
                    ->url(fn ($record): string => $record->url, shouldOpenInNewTab: true)
                    ->color('primary')->limit(50)->searchable(),
                TextColumn::make('platform')->badge()->toggleable()->placeholder('—'),
                TextColumn::make('published_at')->date()->toggleable()->placeholder('—'),
            ])
            ->headerActions([
                CreateAction::make()->label('Add link'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
