<?php

namespace App\Filament\Resources\FinanceImportBatches\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class FinanceImportBatchesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('year')->sortable(),
                TextColumn::make('data_type')->label('Dataset')->badge()
                    ->formatStateUsing(fn (string $state): string => ucfirst($state)),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'completed' => 'success',
                        'failed' => 'danger',
                        'pending' => 'gray',
                        default => 'warning', // downloading | parsing
                    }),
                TextColumn::make('rows_imported')->label('Imported')->numeric()->badge()->color('success'),
                TextColumn::make('rows_skipped')->label('Skipped')->numeric()->toggleable(),
                TextColumn::make('rows_total')->label('Rows seen')->numeric()->toggleable(),
                TextColumn::make('network_connections')->label('Graph edges built')->badge()->color('info')
                    ->formatStateUsing(fn (?int $state, $record): string => $state === null
                        ? '—'
                        : "{$state} from {$record->network_committees} committee(s)")
                    ->tooltip('Donor connections auto-built from this import')
                    ->toggleable(),
                TextColumn::make('source_last_modified')->label('TRACER file dated')->dateTime('M j, Y')->toggleable(),
                TextColumn::make('created_at')->label('Run')->since()->sortable(),
                TextColumn::make('error')->limit(40)->toggleable(isToggledHiddenByDefault: true)->color('danger')->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('status')->options([
                    'pending' => 'Pending', 'downloading' => 'Downloading', 'parsing' => 'Parsing',
                    'completed' => 'Completed', 'failed' => 'Failed',
                ]),
                SelectFilter::make('data_type')->options([
                    'contributions' => 'Contributions', 'expenditures' => 'Expenditures', 'loans' => 'Loans',
                ]),
            ])
            ->recordActions([])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->poll('10s'); // reflect background import progress
    }
}
