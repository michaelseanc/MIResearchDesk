<?php

namespace App\Filament\Resources\Stories\RelationManagers;

use Filament\Actions\AttachAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * People & organizations involved in this story. Entities are ATTACHED (searched from the existing
 * canonical set) rather than created here, to avoid duplicates; a per-story role note is captured
 * on the link.
 */
class EntitiesRelationManager extends RelationManager
{
    protected static string $relationship = 'entities';

    protected static ?string $title = 'People & organizations';

    protected static string|\BackedEnum|null $icon = 'heroicon-o-user-group';

    public function form(Schema $schema): Schema
    {
        // Used by EditAction to edit the pivot role note.
        return $schema->components([
            TextInput::make('role_note')->label('Role in this story')->maxLength(255),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('display_name')
            ->columns([
                TextColumn::make('display_name')->label('Name')->searchable()->weight('medium'),
                TextColumn::make('entity_type')->label('Type')->badge()
                    ->formatStateUsing(fn (string $state): string => ucfirst($state)),
                TextColumn::make('pivot.role_note')->label('Role')
                    ->state(fn ($record): ?string => $record->pivot?->role_note)->placeholder('—'),
            ])
            ->headerActions([
                AttachAction::make()
                    ->label('Add entity')
                    ->preloadRecordSelect()
                    ->recordSelectSearchColumns(['display_name', 'legal_name'])
                    ->schema(fn (AttachAction $action): array => [
                        $action->getRecordSelect(),
                        TextInput::make('role_note')->label('Role in this story')->maxLength(255),
                    ]),
            ])
            ->recordActions([
                EditAction::make()->label('Edit role'),
                DetachAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DetachBulkAction::make(),
                ]),
            ]);
    }
}
