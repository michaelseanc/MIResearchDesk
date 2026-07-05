<?php

namespace App\Filament\Resources\FinanceTransactions\Tables;

use App\Models\Entity;
use App\Models\FinanceTransaction;
use App\Models\Relationship;
use App\Models\RelationshipType;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class FinanceTransactionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('transaction_date')->label('Date')->date()->sortable(),
                TextColumn::make('contributor_name')->label('Contributor / payee')->searchable()->weight('medium')->wrap(),
                TextColumn::make('contributor_type')->label('Type')->badge()->toggleable()->placeholder('—'),
                TextColumn::make('amount')->money('USD')->sortable()->alignEnd(),
                TextColumn::make('occupation')->searchable()->toggleable()->placeholder('—'),
                TextColumn::make('employer')->searchable()->toggleable()->placeholder('—'),
                TextColumn::make('address')->searchable()->toggleable()->wrap()->placeholder('—'),
                TextColumn::make('city')->searchable()->toggleable(),
                TextColumn::make('state')->searchable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('zip')->label('ZIP')->searchable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('committee_name')->label('Committee')->searchable()->wrap(),
                TextColumn::make('candidate_name')->label('Candidate')->searchable()->toggleable()->placeholder('—'),
                TextColumn::make('match_state')
                    ->label('Match')->badge()
                    ->formatStateUsing(fn (string $state): string => ucfirst($state))
                    ->color(fn (string $state): string => match ($state) {
                        'approved' => 'success', 'auto' => 'info', default => 'warning',
                    }),
            ])
            ->filters([
                SelectFilter::make('match_state')
                    ->label('Match state')
                    ->options(['unmatched' => 'Needs review', 'auto' => 'Auto-matched', 'approved' => 'Approved']),
                SelectFilter::make('year')->options(fn (): array => FinanceTransaction::query()
                    ->where('data_type', 'contributions')
                    ->distinct()->orderByDesc('year')->pluck('year', 'year')->all()),
            ])
            ->recordActions([
                self::resolveAction(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('transaction_date', 'desc')
            ->deferLoading(); // paint the page first, then load rows — snappier on large datasets
    }

    /**
     * Resolve a transaction: link the contributor and committee to canonical entities (search first,
     * create as a fallback) and optionally record the money as a "donated to" connection — turning
     * raw finance data into a reviewable, sourced relationship. This is the "follow the money" step.
     */
    private static function resolveAction(): Action
    {
        return Action::make('resolve')
            ->label('Resolve')
            ->icon('heroicon-o-link')
            ->modalWidth('xl')
            ->fillForm(fn (FinanceTransaction $record): array => [
                'contributor_entity_id' => $record->contributor_entity_id,
                'committee_entity_id' => $record->committee_entity_id,
            ])
            ->schema([
                self::entitySelect('contributor_entity_id', 'Contributor entity', 'person'),
                self::entitySelect('committee_entity_id', 'Committee / recipient entity', 'organization'),
                Toggle::make('create_connection')
                    ->label('Also record a “donated to” connection (contributor → committee)')
                    ->helperText('Creates a reported relationship annotated with this contribution.')
                    ->default(false),
            ])
            ->action(function (array $data, FinanceTransaction $record): void {
                $record->update([
                    'contributor_entity_id' => $data['contributor_entity_id'] ?? null,
                    'committee_entity_id' => $data['committee_entity_id'] ?? null,
                    'match_state' => 'approved',
                ]);

                if (! empty($data['create_connection'])
                    && $data['contributor_entity_id']
                    && $data['committee_entity_id']) {
                    $type = RelationshipType::where('name', 'donated_to')->first();

                    Relationship::firstOrCreate(
                        [
                            'from_entity_id' => $data['contributor_entity_id'],
                            'to_entity_id' => $data['committee_entity_id'],
                            'relationship_type_id' => $type?->id,
                        ],
                        [
                            'verification_state' => 'reported',
                            'status' => 'active',
                            'sensitivity' => 'internal',
                            'notes' => sprintf(
                                'TRACER contribution: %s on %s (file #%s). Import-sourced; attach the filing as evidence to verify.',
                                $record->amount ? '$' . number_format((float) $record->amount, 2) : 'amount n/a',
                                $record->transaction_date?->toDateString() ?? 'date n/a',
                                $record->file_number ?? 'n/a',
                            ),
                        ],
                    );

                    Notification::make()->title('Linked and connection recorded')->success()->send();

                    return;
                }

                Notification::make()->title('Transaction resolved')->success()->send();
            });
    }

    /** Searchable entity picker (name/legal/aliases) with an inline create fallback. */
    private static function entitySelect(string $field, string $label, string $defaultType): Select
    {
        return Select::make($field)
            ->label($label)
            ->searchable()
            ->getSearchResultsUsing(fn (string $search): array => Entity::query()
                ->when(! auth()->user()?->can('view_confidential_identity'), fn ($q) => $q->where('sensitivity', '!=', 'sealed'))
                ->where(fn ($q) => $q
                    ->where('display_name', 'like', "%{$search}%")
                    ->orWhere('legal_name', 'like', "%{$search}%")
                    ->orWhereHas('aliases', fn ($a) => $a->where('alias', 'like', "%{$search}%")))
                ->orderBy('display_name')->limit(25)->get()
                ->mapWithKeys(fn (Entity $e): array => [$e->id => $e->display_name . ' — ' . ucfirst($e->entity_type)])
                ->all())
            ->getOptionLabelUsing(fn ($value): ?string => Entity::find($value)?->display_name)
            ->createOptionForm([
                Select::make('entity_type')->label('Type')
                    ->options(['person' => 'Person', 'organization' => 'Organization'])
                    ->default($defaultType)->required(),
                TextInput::make('display_name')->required()->maxLength(255),
            ])
            ->createOptionUsing(fn (array $data): int => Entity::create($data)->getKey());
    }
}
