<?php

namespace App\Filament\Resources\Relationships\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class RelationshipsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('fromEntity.display_name')->label('From')->searchable()->weight('medium'),
                TextColumn::make('type.label')->label('Type')->badge()
                    ->color(fn ($record): string => $record->type?->badgeColor() ?? 'primary'),
                TextColumn::make('toEntity.display_name')->label('To')->searchable()->weight('medium'),
                TextColumn::make('evidence_count')->label('Evidence')->counts('evidence')->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'info' : 'gray'),
                TextColumn::make('verification_state')
                    ->label('Verification')->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'verified' => 'success', 'corroborated' => 'info', 'reported' => 'warning',
                        'lead' => 'gray', 'disputed', 'disproven' => 'danger', default => 'gray',
                    }),
                TextColumn::make('status')->badge()->toggleable(),
                TextColumn::make('issueTag.name')->label('Issue')->toggleable()->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('verification_state')->label('Verification')->options([
                    'lead' => 'Lead', 'reported' => 'Reported', 'corroborated' => 'Corroborated',
                    'verified' => 'Verified', 'disputed' => 'Disputed', 'disproven' => 'Disproven',
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
