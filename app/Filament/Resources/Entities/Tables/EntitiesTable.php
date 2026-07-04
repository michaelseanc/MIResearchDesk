<?php

namespace App\Filament\Resources\Entities\Tables;

use App\Filament\Resources\Entities\EntityResource;
use App\Models\Entity;
use App\Services\EntityMerger;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class EntitiesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('photo_path')
                    ->label('')
                    ->disk('public')
                    ->circular()
                    ->imageSize(40),
                TextColumn::make('display_name')
                    ->label('Name')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),
                TextColumn::make('entity_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => Entity::TYPE_LABELS[$state] ?? ucfirst($state)),
                TextColumn::make('personProfile.current_company')
                    ->label('Company')
                    ->searchable()
                    ->toggleable()
                    ->placeholder('—'),
                TextColumn::make('origin')
                    ->label('Source')
                    ->badge()->color('gray')->toggleable()
                    ->formatStateUsing(fn (?string $state): string => $state === 'finance_import' ? 'Finance import' : 'Curated')
                    ->placeholder('Curated'),
                TextColumn::make('status')->badge()->toggleable(),
                TextColumn::make('primary_geography')->label('Geography')->toggleable(),
                TextColumn::make('sensitivity')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'public' => 'success',
                        'internal' => 'gray',
                        'confidential' => 'warning',
                        'sealed' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('last_reviewed_at')->label('Reviewed')->date()->sortable()->toggleable(),
            ])
            ->filters([
                // On by default: keeps the dossier list to curated subjects, not imported finance actors.
                Filter::make('hide_finance_actors')
                    ->label('Hide imported finance actors')
                    ->toggle()
                    ->default(true)
                    ->query(fn (Builder $query): Builder => $query->where(
                        fn (Builder $q) => $q->whereNull('origin')->orWhere('origin', '!=', 'finance_import'),
                    )),
                SelectFilter::make('entity_type')
                    ->label('Type')
                    ->options(Entity::TYPE_LABELS),
                SelectFilter::make('sensitivity')
                    ->options([
                        'public' => 'Public',
                        'internal' => 'Internal',
                        'confidential' => 'Confidential',
                    ]),
                TrashedFilter::make(),
            ])
            ->recordActions([
                Action::make('adopt')
                    ->label('Adopt as dossier')
                    ->icon('heroicon-o-bookmark')
                    ->visible(fn (Entity $record): bool => $record->origin === 'finance_import')
                    ->requiresConfirmation()
                    ->modalDescription('Promote this imported finance actor into a curated dossier subject. It will then appear in the main list.')
                    ->action(function (Entity $record): void {
                        $record->update(['origin' => null]);
                        Notification::make()->title('Adopted into dossiers')->success()->send();
                    }),
                // No explicit Edit action — clicking the row already opens the record for editing.
                Action::make('duplicate')
                    ->label('Duplicate')
                    ->icon('heroicon-o-document-duplicate')
                    ->modalHeading('Duplicate as a new entity')
                    ->modalDescription('Creates a new record that keeps only the shared context (type, employer/company, location, jurisdiction). The name, photo, summaries, connections, and contacts are NOT copied.')
                    ->schema([
                        TextInput::make('display_name')->label('New name')->required()->maxLength(255)
                            ->helperText('e.g. a colleague at the same organization.'),
                    ])
                    ->action(function (array $data, Entity $record, $livewire) {
                        $new = Entity::create([
                            'entity_type' => $record->entity_type,
                            'display_name' => $data['display_name'],
                            'status' => $record->status,
                            'sensitivity' => $record->sensitivity,
                            'primary_geography' => $record->primary_geography,
                            'primary_jurisdiction_id' => $record->primary_jurisdiction_id,
                        ]);

                        // Carry over only the shared affiliation/location — the point of the shortcut.
                        if ($record->entity_type === 'person' && $record->personProfile) {
                            $new->personProfile()->create([
                                'current_company' => $record->personProfile->current_company,
                                'current_company_entity_id' => $record->personProfile->current_company_entity_id,
                                'geography_detail' => $record->personProfile->geography_detail,
                            ]);
                        }

                        if ($record->isOrganizationLike() && $record->organizationProfile) {
                            $new->organizationProfile()->create([
                                'org_subtype' => $record->organizationProfile->org_subtype,
                                'jurisdiction_id' => $record->organizationProfile->jurisdiction_id,
                            ]);
                        }

                        Notification::make()->title('New entity created — fill in the details')->success()->send();

                        $livewire->redirect(EntityResource::getUrl('edit', ['record' => $new]), navigate: true);
                    }),
                Action::make('merge')
                    ->label('Merge into…')
                    ->icon('heroicon-o-arrows-pointing-in')
                    ->color('warning')
                    ->modalHeading(fn (Entity $record): string => "Merge “{$record->display_name}” into another entity")
                    ->modalDescription('All connections, TRACER donations, contacts, notes, and history move to the entity you choose — then this duplicate is removed. This cannot be undone.')
                    ->schema([
                        Select::make('keeper_id')
                            ->label('Keep this entity (merge into)')
                            ->required()
                            ->searchable()
                            ->getSearchResultsUsing(fn (string $search, Entity $record): array => Entity::query()
                                ->whereKeyNot($record->getKey())
                                ->where(fn ($q) => $q->where('display_name', 'like', "%{$search}%")
                                    ->orWhere('legal_name', 'like', "%{$search}%"))
                                ->orderBy('display_name')->limit(20)
                                ->pluck('display_name', 'id')->all())
                            ->getOptionLabelUsing(fn ($value): ?string => Entity::find($value)?->display_name)
                            ->helperText('The record you pick is kept; the one you clicked is merged into it.'),
                    ])
                    ->action(function (array $data, Entity $record, $livewire): void {
                        $keeper = Entity::findOrFail($data['keeper_id']);
                        app(EntityMerger::class)->merge($record, $keeper);

                        Notification::make()
                            ->title('Merged')
                            ->body("Everything moved into “{$keeper->display_name}”.")
                            ->success()->send();

                        $livewire->redirect(EntityResource::getUrl('edit', ['record' => $keeper->getKey()]), navigate: true);
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ])
            ->defaultSort('display_name');
    }
}
