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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Colorado TRACER — political donations this entity RECEIVED (it is the recipient committee or the
 * named candidate). The "Match" action links TRACER records filed to this committee/candidate name.
 */
class DonationsReceivedRelationManager extends RelationManager
{
    protected static string $relationship = 'donationsToCommittee';

    protected static ?string $title = 'Donations received';

    protected static string|\BackedEnum|null $icon = 'heroicon-o-arrow-down-left';

    /** Recipient can be the committee OR the named candidate. */
    protected function getTableQuery(): Builder|Relation|null
    {
        $ownerId = $this->getOwnerRecord()->getKey();

        return FinanceTransaction::query()
            ->where('data_type', 'contributions')
            ->where(fn (Builder $q) => $q
                ->where('committee_entity_id', $ownerId)
                ->orWhere('candidate_entity_id', $ownerId));
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        $ownerId = $this->getOwnerRecord()->getKey();

        return $table
            ->recordTitleAttribute('contributor_name')
            ->columns([
                TextColumn::make('transaction_date')->label('Date')->date()->sortable(),
                TextColumn::make('amount')->money('USD')->sortable()->alignEnd()
                    ->summarize(Sum::make()->money('USD')->label('Total received')),
                TextColumn::make('contributor_name')->label('From (contributor)')->searchable()->wrap(),
                TextColumn::make('employer')->searchable()->toggleable()->placeholder('—'),
                TextColumn::make('city')->searchable()->toggleable()->placeholder('—'),
                TextColumn::make('committee_name')->label('Committee')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->headerActions([
                $this->matchAction($ownerId, $this->suggestedRecipientNames()),
            ])
            ->recordActions([
                Action::make('unlink')
                    ->label('Unlink')->icon('heroicon-o-x-mark')->color('gray')->iconButton()
                    ->requiresConfirmation()
                    ->action(function (FinanceTransaction $record) use ($ownerId): void {
                        $record->update([
                            'committee_entity_id' => $record->committee_entity_id === $ownerId ? null : $record->committee_entity_id,
                            'candidate_entity_id' => $record->candidate_entity_id === $ownerId ? null : $record->candidate_entity_id,
                        ]);
                    }),
            ])
            ->defaultSort('transaction_date', 'desc')
            ->emptyStateHeading('No linked donations received')
            ->emptyStateDescription('Use “Match TRACER donations” to link contributions to this committee/candidate.');
    }

    /** Unlinked committee/candidate names matching this entity's name tokens — used to pre-select. */
    protected function suggestedRecipientNames(): array
    {
        $tokens = collect(preg_split('/\s+/', strtolower(trim((string) $this->getOwnerRecord()->display_name))))
            ->filter(fn ($t) => strlen($t) > 2)->values();
        if ($tokens->isEmpty()) {
            return [];
        }

        $committees = FinanceTransaction::query()
            ->whereNull('committee_entity_id')->where('data_type', 'contributions')->whereNotNull('committee_name');
        $candidates = FinanceTransaction::query()
            ->whereNull('candidate_entity_id')->where('data_type', 'contributions')->whereNotNull('candidate_name');
        foreach ($tokens as $token) {
            $committees->where('committee_name', 'like', "%{$token}%");
            $candidates->where('candidate_name', 'like', "%{$token}%");
        }

        return array_values(array_unique(array_merge(
            $committees->distinct()->limit(50)->pluck('committee_name')->all(),
            $candidates->distinct()->limit(50)->pluck('candidate_name')->all(),
        )));
    }

    /** Link unlinked TRACER contributions whose committee/candidate name the user confirms is this entity. */
    protected function matchAction(int $ownerId, array $suggested = []): Action
    {
        return Action::make('matchRecipient')
            ->label('Match TRACER donations')
            ->icon('heroicon-o-link')
            ->color('primary')
            ->modalWidth('xl')
            ->modalDescription('Likely matches for this entity\'s name are pre-selected below — review and deselect any that aren\'t them, or search to add more.')
            ->schema([
                Select::make('names')
                    ->label('Committee / candidate names in TRACER')
                    ->multiple()
                    ->searchable()
                    ->default($suggested)
                    ->getSearchResultsUsing(function (string $search): array {
                        $committees = FinanceTransaction::query()
                            ->whereNull('committee_entity_id')->where('data_type', 'contributions')
                            ->whereNotNull('committee_name')->where('committee_name', 'like', "%{$search}%")
                            ->distinct()->limit(30)->pluck('committee_name', 'committee_name')->all();
                        $candidates = FinanceTransaction::query()
                            ->whereNull('candidate_entity_id')->where('data_type', 'contributions')
                            ->whereNotNull('candidate_name')->where('candidate_name', 'like', "%{$search}%")
                            ->distinct()->limit(30)->pluck('candidate_name', 'candidate_name')->all();

                        return $committees + $candidates;
                    })
                    ->getOptionLabelsUsing(fn (array $values): array => array_combine($values, $values)),
            ])
            ->action(function (array $data) use ($ownerId): void {
                $names = array_filter($data['names'] ?? []);
                if (! $names) {
                    return;
                }

                $c1 = FinanceTransaction::whereNull('committee_entity_id')
                    ->where('data_type', 'contributions')->whereIn('committee_name', $names)
                    ->update(['committee_entity_id' => $ownerId, 'match_state' => 'approved']);
                $c2 = FinanceTransaction::whereNull('candidate_entity_id')
                    ->where('data_type', 'contributions')->whereIn('candidate_name', $names)
                    ->update(['candidate_entity_id' => $ownerId]);

                Notification::make()->title('Linked ' . ($c1 + $c2) . ' contribution(s)')->success()->send();
            });
    }
}
