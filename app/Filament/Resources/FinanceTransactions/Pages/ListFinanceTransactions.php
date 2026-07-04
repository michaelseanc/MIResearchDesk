<?php

namespace App\Filament\Resources\FinanceTransactions\Pages;

use App\Filament\Resources\FinanceTransactions\FinanceTransactionResource;
use App\Jobs\BuildCommitteeNetworks;
use App\Models\FinanceTransaction;
use App\Services\Finance\FinanceNetworkBuilder;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListFinanceTransactions extends ListRecords
{
    protected static string $resource = FinanceTransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                $this->buildNetworkAction(),
                $this->buildAllNetworksAction(),
            ])
                ->label('Build network')
                ->icon('heroicon-o-share')
                ->button(),
        ];
    }

    /**
     * Auto-build donation networks for every committee above a total floor, in one pass. Runs on
     * the queue since it can create many entities/edges.
     */
    protected function buildAllNetworksAction(): Action
    {
        return Action::make('buildAllNetworks')
            ->label('Auto-build all committees')
            ->icon('heroicon-o-squares-plus')
            ->modalWidth('xl')
            ->modalDescription('Builds committee→donor networks across the whole dataset at once. Thresholds keep it to consequential money — lower them to include more, raise them to stay focused. Safe to re-run.')
            ->schema([
                TextInput::make('min_committee')
                    ->label('Only committees receiving at least ($)')
                    ->numeric()->default(10000)->required()
                    ->helperText('Skips tiny committees.'),
                TextInput::make('min_donor')
                    ->label('Only donors giving at least ($) to a committee')
                    ->numeric()->default(1000)->required(),
                TextInput::make('cap')
                    ->label('Max donors per committee')
                    ->numeric()->default(50)->required()->minValue(1)->maxValue(500),
            ])
            ->action(function (array $data): void {
                BuildCommitteeNetworks::dispatch(
                    auth()->user()->organization_id,
                    (float) $data['min_committee'],
                    (float) $data['min_donor'],
                    (int) $data['cap'],
                );

                Notification::make()
                    ->title('Building networks in the background')
                    ->body('Committee and donor entities + donation connections are being created across the dataset. Open the Relationship graph shortly to explore them.')
                    ->success()
                    ->send();
            });
    }

    /**
     * Promote a committee and its significant donors into entities and create aggregated donation
     * edges, so the money appears on the relationship graph in a few clicks instead of thousands.
     */
    protected function buildNetworkAction(): Action
    {
        return Action::make('buildNetwork')
            ->label('Build network from committee')
            ->icon('heroicon-o-share')
            ->color('primary')
            ->modalWidth('xl')
            ->modalDescription('Creates an entity for the committee and for each donor above your threshold, then adds one “donated to” connection per donor. Runs on the whole dataset, not just the current filter.')
            ->schema([
                Select::make('committee_name')
                    ->label('Committee')
                    ->required()
                    ->searchable()
                    ->getSearchResultsUsing(fn (string $search): array => FinanceTransaction::query()
                        ->where('committee_name', 'like', "%{$search}%")
                        ->whereNotNull('committee_name')
                        ->distinct()->orderBy('committee_name')->limit(30)
                        ->pluck('committee_name', 'committee_name')->all())
                    ->getOptionLabelUsing(fn ($value): string => (string) $value),
                TextInput::make('min_total')
                    ->label('Only donors giving at least ($)')
                    ->numeric()->default(1000)->required()
                    ->helperText('Keeps the graph to consequential money. Lower it to include smaller donors.'),
                TextInput::make('max_donors')
                    ->label('Cap number of donors')
                    ->numeric()->default(100)->required()->minValue(1)->maxValue(500),
            ])
            ->action(function (array $data): void {
                $result = app(FinanceNetworkBuilder::class)->buildFromCommittee(
                    $data['committee_name'],
                    (float) $data['min_total'],
                    (int) $data['max_donors'],
                );

                Notification::make()
                    ->title('Network built')
                    ->body("Committee entity + {$result['donors_promoted']} donor(s), {$result['connections']} donation connection(s). Open the Relationship graph and focus the committee to see it.")
                    ->success()
                    ->send();
            });
    }
}
