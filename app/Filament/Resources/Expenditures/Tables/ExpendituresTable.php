<?php

namespace App\Filament\Resources\Expenditures\Tables;

use App\Models\FinanceTransaction;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ExpendituresTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('transaction_date')->label('Date')->date()->sortable(),
                TextColumn::make('contributor_name')->label('Payee / vendor')->searchable()->weight('medium')->wrap(),
                TextColumn::make('txn_subtype')->label('Type')->badge()->toggleable()->placeholder('—'),
                TextColumn::make('amount')->label('Amount')->money('USD')->sortable()->alignEnd()
                    ->summarize(Sum::make()->money('USD')->label('Total spent')),
                TextColumn::make('description')->label('Purpose')->searchable()->wrap()->toggleable()->placeholder('—'),
                TextColumn::make('committee_name')->label('Committee (spender)')->searchable()->wrap(),
                TextColumn::make('candidate_name')->label('Candidate')->searchable()->toggleable()->placeholder('—'),
                TextColumn::make('city')->searchable()->toggleable(),
                TextColumn::make('jurisdiction')->label('County')->searchable()->toggleable()->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('year')->options(fn (): array => FinanceTransaction::query()
                    ->where('data_type', 'expenditures')
                    ->distinct()->orderByDesc('year')->pluck('year', 'year')->all()),
                SelectFilter::make('jurisdiction')
                    ->label('County')
                    ->options(fn (): array => FinanceTransaction::query()
                        ->where('data_type', 'expenditures')
                        ->whereNotNull('jurisdiction')->where('jurisdiction', '!=', '')
                        ->distinct()->orderBy('jurisdiction')->pluck('jurisdiction', 'jurisdiction')->all()),
            ])
            ->defaultSort('transaction_date', 'desc')
            ->emptyStateHeading('No expenditures imported')
            ->emptyStateDescription('Use TRACER imports → Expenditures to pull committee spending.');
    }
}
