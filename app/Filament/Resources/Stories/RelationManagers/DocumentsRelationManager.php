<?php

namespace App\Filament\Resources\Stories\RelationManagers;

use Filament\Actions\AttachAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Documents linked to this story. Attached from the existing document library.
 */
class DocumentsRelationManager extends RelationManager
{
    protected static string $relationship = 'documents';

    protected static ?string $title = 'Documents';

    protected static string|\BackedEnum|null $icon = 'heroicon-o-document-text';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->columns([
                TextColumn::make('title')->searchable()->weight('medium')->wrap(),
                TextColumn::make('source_type')->label('Source')->badge()->toggleable()
                    ->formatStateUsing(fn (?string $state): string => $state ? ucwords(str_replace('_', ' ', $state)) : '—'),
                TextColumn::make('sensitivity')->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'public' => 'success', 'internal' => 'gray', 'confidential' => 'warning', 'sealed' => 'danger', default => 'gray',
                    }),
                TextColumn::make('document_date')->label('Dated')->date()->toggleable(),
            ])
            ->headerActions([
                AttachAction::make()
                    ->label('Attach document')
                    ->preloadRecordSelect()
                    ->recordSelectSearchColumns(['title']),
            ])
            ->recordActions([
                DetachAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DetachBulkAction::make(),
                ]),
            ]);
    }
}
