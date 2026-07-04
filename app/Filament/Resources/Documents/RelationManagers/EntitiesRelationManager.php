<?php

namespace App\Filament\Resources\Documents\RelationManagers;

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
use Illuminate\Database\Eloquent\Builder;

/**
 * People & organizations this document concerns. Link an uploaded document to any number of
 * existing entities (searched to avoid duplicates).
 */
class EntitiesRelationManager extends RelationManager
{
    protected static string $relationship = 'entities';

    protected static ?string $title = 'Linked people & organizations';

    protected static string|\BackedEnum|null $icon = 'heroicon-o-user-group';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('note')->label('Note on this link')->maxLength(255),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('display_name')
            ->modifyQueryUsing(function (Builder $query): Builder {
                if (! auth()->user()?->can('view_confidential_identity')) {
                    $query->where('entities.sensitivity', '!=', 'sealed');
                }

                return $query;
            })
            ->columns([
                TextColumn::make('display_name')->label('Name')->searchable()->weight('medium'),
                TextColumn::make('entity_type')->label('Type')->badge()
                    ->formatStateUsing(fn (string $state): string => ucfirst($state)),
                TextColumn::make('pivot.note')->label('Link note')
                    ->state(fn ($record): ?string => $record->pivot?->note)->placeholder('—')->toggleable(),
            ])
            ->headerActions([
                AttachAction::make()
                    ->label('Link entity')
                    ->recordSelectSearchColumns(['display_name', 'legal_name'])
                    ->schema(fn (AttachAction $action): array => [
                        $action->getRecordSelect(),
                        TextInput::make('note')->label('Note on this link')->maxLength(255),
                    ]),
            ])
            ->recordActions([
                EditAction::make()->label('Edit note'),
                DetachAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DetachBulkAction::make(),
                ]),
            ]);
    }
}
