<?php

namespace App\Filament\Resources\Loans\Tables;

use App\Models\FinanceTransaction;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class LoansTable
{
    public static function configure(Table $table): Table
    {
        $money = fn (?string $state): string => $state === null || $state === ''
            ? '—'
            : '$' . number_format((float) $state, 2);

        return $table
            ->columns([
                TextColumn::make('transaction_date')->label('Loan date')->date()->sortable(),
                TextColumn::make('contributor_name')->label('Lender')->searchable()->weight('medium')->wrap(),
                TextColumn::make('committee_name')->label('Committee (borrower)')->searchable()->wrap(),
                TextColumn::make('amount')->label('Loan amount')->money('USD')->sortable()->alignEnd()
                    ->summarize(Sum::make()->money('USD')->label('Total loaned')),
                TextColumn::make('loan_balance')->label('Balance')->alignEnd()
                    ->state(fn (FinanceTransaction $record): ?string => $record->source_extra['loan_balance'] ?? null)
                    ->formatStateUsing(fn (?string $state): string => $money($state)),
                TextColumn::make('interest_rate')->label('Rate')->alignEnd()->toggleable()
                    ->state(fn (FinanceTransaction $record): ?string => $record->source_extra['interest_rate'] ?? null)
                    ->formatStateUsing(fn (?string $state): string => $state === null || $state === ''
                        ? '—'
                        : rtrim(rtrim(number_format((float) $state, 2), '0'), '.') . '%'),
                TextColumn::make('txn_subtype')->label('Source type')->badge()->toggleable()->placeholder('—'),
                TextColumn::make('candidate_name')->label('Candidate')->searchable()->toggleable()->placeholder('—'),
                TextColumn::make('jurisdiction')->label('County')->searchable()->toggleable()->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('year')->options(fn (): array => FinanceTransaction::query()
                    ->where('data_type', 'loans')
                    ->distinct()->orderByDesc('year')->pluck('year', 'year')->all()),
                SelectFilter::make('jurisdiction')
                    ->label('County')
                    ->options(fn (): array => FinanceTransaction::query()
                        ->where('data_type', 'loans')
                        ->whereNotNull('jurisdiction')->where('jurisdiction', '!=', '')
                        ->distinct()->orderBy('jurisdiction')->pluck('jurisdiction', 'jurisdiction')->all()),
            ])
            ->defaultSort('transaction_date', 'desc')
            ->deferLoading()
            ->emptyStateHeading('No loans imported')
            ->emptyStateDescription('Use TRACER imports → Loans to pull committee loans.');
    }
}
