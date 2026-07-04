<?php

namespace App\Filament\Resources\Documents\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Page-level citations within a document. These are the atomic evidence units that relationships,
 * positions, and claims cite.
 */
class CitationsRelationManager extends RelationManager
{
    protected static string $relationship = 'citations';

    protected static ?string $title = 'Citations';

    protected static string|\BackedEnum|null $icon = 'heroicon-o-bookmark';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('page')->numeric()->label('Page'),
            TextInput::make('paragraph')->label('Paragraph / section')->maxLength(255),
            Textarea::make('quote')->label('Quoted text')->rows(3)->columnSpanFull(),
            Textarea::make('note')->label('Reporter note')->rows(2)->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('quote')
            ->columns([
                TextColumn::make('page')->sortable(),
                TextColumn::make('paragraph')->toggleable()->placeholder('—'),
                TextColumn::make('quote')->limit(80)->wrap()->placeholder('—'),
                TextColumn::make('note')->limit(50)->toggleable()->placeholder('—'),
            ])
            ->headerActions([
                CreateAction::make()->label('Add citation'),
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
            ->defaultSort('page');
    }
}
