<?php

namespace App\Filament\Resources\RelationshipTypes\Tables;

use App\Models\RelationshipType;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RelationshipTypesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                // The label rendered in its actual badge color, so the color is previewable here.
                TextColumn::make('label')->badge()
                    ->color(fn (RelationshipType $record): string => $record->badgeColor()),
                TextColumn::make('inverse_name')->label('Reverse')->toggleable()->placeholder('—'),
                IconColumn::make('is_directional')->label('Directional')->boolean(),
                TextColumn::make('category')->badge()->toggleable()->placeholder('—'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('label');
    }
}
