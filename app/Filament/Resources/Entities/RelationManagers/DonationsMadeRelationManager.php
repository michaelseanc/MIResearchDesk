<?php

namespace App\Filament\Resources\Entities\RelationManagers;

use App\Models\FinanceTransaction;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Colorado TRACER — political donations this entity MADE (it is the contributor). Rows are TRACER
 * contributions whose contributor is linked to this entity; the "Match" action links TRACER records
 * filed under this person/org's name that aren't linked yet.
 */
class DonationsMadeRelationManager extends RelationManager
{
    protected static string $relationship = 'donationsMade';

    protected static ?string $title = 'Donations made';

    protected static string|\BackedEnum|null $icon = 'heroicon-o-arrow-up-right';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        $ownerId = $this->getOwnerRecord()->getKey();

        return $table
            ->recordTitleAttribute('committee_name')
            ->columns([
                TextColumn::make('transaction_date')->label('Date')->date()->sortable(),
                TextColumn::make('amount')->money('USD')->sortable()->alignEnd()
                    ->summarize(Sum::make()->money('USD')->label('Total given')),
                TextColumn::make('committee_name')->label('To (committee)')->searchable()->wrap(),
                TextColumn::make('candidate_name')->label('Candidate')->toggleable()->placeholder('—'),
                TextColumn::make('txn_subtype')->label('Type')->toggleable()->placeholder('—'),
            ])
            ->headerActions([
                $this->matchAction($ownerId, $this->suggestedContributorNames()),
            ])
            ->recordActions([
                Action::make('unlink')
                    ->label('Unlink')->icon('heroicon-o-x-mark')->color('gray')->iconButton()
                    ->tooltip('This TRACER record isn’t this entity — unlink it')
                    ->requiresConfirmation()
                    ->action(fn (FinanceTransaction $record) => $record->update([
                        'contributor_entity_id' => null, 'match_state' => 'unmatched',
                    ])),
            ])
            ->defaultSort('transaction_date', 'desc')
            ->emptyStateHeading('No linked donations made')
            ->emptyStateDescription('Use “Match TRACER donations” to link contributions filed under this name.');
    }

    /** Unlinked TRACER donor names that match any of this entity's names — used to pre-select. */
    protected function suggestedContributorNames(): array
    {
        $results = collect();

        // Entity::nameTokenGroups() is entity-type aware (business names for orgs) and drops
        // stopwords/suffixes so "The Platinum Group" still matches a "PLATINUM GROUP" record.
        foreach ($this->getOwnerRecord()->nameTokenGroups() as $tokens) {
            $query = FinanceTransaction::query()
                ->whereNull('contributor_entity_id')->where('data_type', 'contributions')->whereNotNull('contributor_name');
            foreach ($tokens as $token) {
                $query->where('contributor_name', 'like', "%{$token}%");
            }

            $results = $results->merge($query->distinct()->limit(50)->pluck('contributor_name'));
        }

        return $results->unique()->sort()->values()->take(50)->all();
    }

    /** Link unlinked TRACER contributions whose donor name the user confirms is this entity. */
    protected function matchAction(int $ownerId, array $suggested = []): Action
    {
        return Action::make('matchDonor')
            ->label('Match TRACER donations')
            ->icon('heroicon-o-link')
            ->color('primary')
            ->modalWidth('xl')
            ->modalDescription('Likely matches for this entity\'s name are pre-selected below — review and deselect any that aren\'t them, or search to add more.')
            ->schema([
                Select::make('names')
                    ->label('Donor names in TRACER')
                    ->multiple()
                    ->searchable()
                    ->default($suggested)
                    ->getSearchResultsUsing(fn (string $search): array => FinanceTransaction::query()
                        ->whereNull('contributor_entity_id')
                        ->where('data_type', 'contributions')
                        ->whereNotNull('contributor_name')
                        ->where('contributor_name', 'like', "%{$search}%")
                        ->distinct()->orderBy('contributor_name')->limit(40)
                        ->pluck('contributor_name', 'contributor_name')->all())
                    ->getOptionLabelsUsing(fn (array $values): array => array_combine($values, $values))
                    ->helperText('Pre-filled from this entity’s name. Search (e.g. “Graham”) to add variants.'),
            ])
            ->action(function (array $data) use ($ownerId): void {
                $names = array_filter($data['names'] ?? []);
                if (! $names) {
                    return;
                }

                $count = FinanceTransaction::whereNull('contributor_entity_id')
                    ->where('data_type', 'contributions')
                    ->whereIn('contributor_name', $names)
                    ->update(['contributor_entity_id' => $ownerId, 'match_state' => 'approved']);

                Notification::make()->title("Linked {$count} contribution(s)")->success()->send();
            });
    }
}
