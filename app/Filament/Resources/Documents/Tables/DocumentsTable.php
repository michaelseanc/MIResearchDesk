<?php

namespace App\Filament\Resources\Documents\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class DocumentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->searchable()->sortable()->weight('medium')->wrap(),
                TextColumn::make('source_type')->label('Source')->badge()->toggleable()
                    ->formatStateUsing(fn (?string $state): string => $state ? ucwords(str_replace('_', ' ', $state)) : '—'),
                TextColumn::make('citations_count')->label('Citations')->counts('citations')->badge()->color('info'),
                TextColumn::make('sensitivity')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'public' => 'success', 'internal' => 'gray', 'confidential' => 'warning', 'sealed' => 'danger', default => 'gray',
                    }),
                TextColumn::make('document_date')->label('Dated')->date()->sortable()->toggleable(),
                TextColumn::make('created_at')->label('Added')->date()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('source_type')->options([
                    'public_record' => 'Public record',
                    'campaign_filing' => 'Campaign filing',
                    'meeting_packet' => 'Meeting packet',
                    'court_filing' => 'Court filing',
                    'foia' => 'FOIA',
                ]),
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
