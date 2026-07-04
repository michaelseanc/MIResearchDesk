<?php

namespace App\Filament\Resources\Entities\RelationManagers;

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
 * Documents/evidence linked to this entity. Documents are uploaded once in the Documents library
 * and then attached here (searched by title), avoiding duplicate uploads.
 */
class DocumentsRelationManager extends RelationManager
{
    protected static string $relationship = 'documents';

    protected static ?string $title = 'Documents & evidence';

    protected static string|\BackedEnum|null $icon = 'heroicon-o-paper-clip';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('note')->label('Note on this link')->maxLength(255),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->modifyQueryUsing(function (Builder $query): Builder {
                if (! auth()->user()?->can('view_restricted_documents')) {
                    $query->where('documents.sensitivity', '!=', 'sealed');
                }

                return $query;
            })
            ->columns([
                TextColumn::make('title')->searchable()->weight('medium')->wrap(),
                TextColumn::make('source_type')->label('Source')->badge()->toggleable()
                    ->formatStateUsing(fn (?string $state): string => $state ? ucwords(str_replace('_', ' ', $state)) : '—'),
                TextColumn::make('sensitivity')->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'public' => 'success', 'internal' => 'gray', 'confidential' => 'warning', 'sealed' => 'danger', default => 'gray',
                    }),
                TextColumn::make('pivot.note')->label('Link note')
                    ->state(fn ($record): ?string => $record->pivot?->note)->placeholder('—')->toggleable(),
            ])
            ->headerActions([
                AttachAction::make()
                    ->label('Link document')
                    ->recordSelectSearchColumns(['title'])
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
