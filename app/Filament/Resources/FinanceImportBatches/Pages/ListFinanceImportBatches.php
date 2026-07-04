<?php

namespace App\Filament\Resources\FinanceImportBatches\Pages;

use App\Filament\Resources\FinanceImportBatches\FinanceImportBatchResource;
use App\Jobs\ImportTracerData;
use App\Models\Organization;
use App\Services\Finance\FinanceNetworkBuilder;
use App\Services\Finance\TracerImporter;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListFinanceImportBatches extends ListRecords
{
    protected static string $resource = FinanceImportBatchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->importAction(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            \App\Filament\Resources\FinanceImportBatches\Widgets\PendingImportsWarning::class,
        ];
    }

    /**
     * Pull directly from TRACER: creates a batch and queues the download+parse of the official
     * bulk file. Filters are saved to the organization so the weekly auto-refresh reuses them.
     */
    protected function importAction(): Action
    {
        $org = Organization::withoutGlobalScopes()->find(auth()->user()->organization_id);
        $saved = $org?->settings['finance_filter'] ?? [];
        $net = Organization::networkConfig(auth()->user()->organization_id);

        return Action::make('importTracer')
            ->label('Import from TRACER')
            ->icon('heroicon-o-arrow-down-tray')
            ->color('primary')
            ->modalWidth('xl')
            ->modalDescription('Downloads Colorado TRACER\'s official bulk file and imports only rows matching your filters. Leave filters empty to import the entire state file (large).')
            ->schema([
                Select::make('data_type')
                    ->label('Dataset')
                    ->options([
                        'contributions' => 'Contributions',
                        'expenditures' => 'Expenditures',
                        'loans' => 'Loans',
                    ])
                    ->default('contributions')->required()
                    ->helperText('All three pull from the matching TRACER bulk file for the year below.'),
                TextInput::make('year')->numeric()->default(now()->year)->required()->minValue(2000)->maxValue(2100),
                Select::make('counties')
                    ->label('Counties')
                    ->multiple()
                    ->searchable()
                    ->options(collect(TracerImporter::COUNTIES)
                        ->mapWithKeys(fn (string $c) => [$c => ucwords(strtolower($c))])->all())
                    ->default($saved['counties'] ?? [])
                    ->helperText('Keeps races in these counties (TRACER\'s "Jurisdiction"). Statewide/federal races have no county — use name terms for those.'),
                TagsInput::make('terms')
                    ->label('Committee / candidate / donor name contains')
                    ->placeholder('Monument, Tri-View, Buc-ee')
                    ->default($saved['terms'] ?? []),
                TagsInput::make('cities')->label('Cities')->default($saved['cities'] ?? []),
                TagsInput::make('zips')->label('ZIP codes')->default($saved['zips'] ?? []),
                TextInput::make('net_min_committee')
                    ->label('Auto-build: min committee total ($)')
                    ->numeric()->minValue(0)->default($net['min_committee_total'])
                    ->helperText('After a contributions import, donor networks auto-build for committees whose total clears this. Lower it to include small local races.'),
                TextInput::make('net_min_donor')
                    ->label('Auto-build: min donor total ($)')
                    ->numeric()->minValue(0)->default($net['min_donor_total'])
                    ->helperText('Donors giving at least this become entities/connections. 0 = every donor.'),
                TextInput::make('net_max_donors')
                    ->label('Auto-build: max donors per committee')
                    ->numeric()->minValue(1)->default($net['max_donors_per_committee']),
            ])
            ->action(function (array $data): void {
                $filter = [
                    'terms' => $data['terms'] ?? [],
                    'counties' => $data['counties'] ?? [],
                    'cities' => $data['cities'] ?? [],
                    'zips' => $data['zips'] ?? [],
                ];

                $orgId = auth()->user()->organization_id;

                // Persist the filter + network thresholds so the weekly scheduler reuses them.
                $org = Organization::withoutGlobalScopes()->find($orgId);
                $settings = $org->settings ?? [];
                $settings['finance_filter'] = $filter;
                $settings['finance_network'] = [
                    'min_committee_total' => (float) ($data['net_min_committee'] ?? FinanceNetworkBuilder::DEFAULT_MIN_COMMITTEE_TOTAL),
                    'min_donor_total' => (float) ($data['net_min_donor'] ?? FinanceNetworkBuilder::DEFAULT_MIN_DONOR_TOTAL),
                    'max_donors_per_committee' => (int) ($data['net_max_donors'] ?? FinanceNetworkBuilder::DEFAULT_MAX_DONORS_PER_COMMITTEE),
                ];
                $org->update(['settings' => $settings]);

                $batch = TracerImporter::createBatch($orgId, $data['data_type'], (int) $data['year'], $filter);
                ImportTracerData::dispatch($batch->id);

                Notification::make()
                    ->title('TRACER import queued')
                    ->body("Batch #{$batch->id} is downloading and processing in the background. Refresh in a moment to see counts.")
                    ->success()
                    ->send();
            });
    }
}
