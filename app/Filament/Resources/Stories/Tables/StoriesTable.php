<?php

namespace App\Filament\Resources\Stories\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class StoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->searchable()->sortable()->weight('medium')->wrap(),
                TextColumn::make('type')->badge()->toggleable()
                    ->formatStateUsing(fn (string $state): string => ucwords(str_replace('_', ' ', $state))),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ucwords(str_replace('_', ' ', $state)))
                    ->color(fn (string $state): string => match ($state) {
                        'published' => 'success',
                        'legal_review' => 'danger',
                        'edit', 'draft' => 'info',
                        'archived' => 'gray',
                        default => 'warning',
                    }),
                TextColumn::make('priority')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'urgent' => 'danger', 'high' => 'warning', 'normal' => 'gray', 'low' => 'gray', default => 'gray',
                    }),
                TextColumn::make('entities_count')->label('Entities')->counts('entities')->badge()->toggleable(),
                TextColumn::make('updated_at')->label('Updated')->since()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options([
                    'lead' => 'Lead', 'reporting' => 'Reporting', 'records_pending' => 'Records pending',
                    'draft' => 'Draft', 'edit' => 'Edit', 'legal_review' => 'Legal review',
                    'published' => 'Published', 'follow_up' => 'Follow-up', 'archived' => 'Archived',
                ]),
                SelectFilter::make('type')->options([
                    'story' => 'Story', 'investigation' => 'Investigation', 'ongoing_issue' => 'Ongoing issue',
                    'beat' => 'Beat', 'project' => 'Project',
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
            ->defaultSort('updated_at', 'desc');
    }
}
