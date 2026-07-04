<?php

namespace App\Filament\Resources\Stories\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Table;

/**
 * Reporting to-dos for a story: interviews to make, records to pull, calls to return.
 */
class TasksRelationManager extends RelationManager
{
    protected static string $relationship = 'tasks';

    protected static ?string $title = 'Reporting tasks';

    protected static string|\BackedEnum|null $icon = 'heroicon-o-check-circle';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')->required()->maxLength(255)->columnSpanFull(),
            Select::make('assigned_to')
                ->label('Assigned to')
                ->relationship('assignee', 'name')
                ->searchable()->preload(),
            Select::make('status')
                ->options(['open' => 'Open', 'in_progress' => 'In progress', 'done' => 'Done'])
                ->default('open')->required(),
            DateTimePicker::make('due_at')->label('Due')->seconds(false),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->columns([
                IconColumn::make('status')
                    ->label('')
                    ->icon(fn (string $state): string => $state === 'done' ? 'heroicon-s-check-circle' : 'heroicon-o-clock')
                    ->color(fn (string $state): string => $state === 'done' ? 'success' : 'gray'),
                TextColumn::make('title')->searchable()->wrap()->weight('medium'),
                TextColumn::make('assignee.name')->label('Assignee')->placeholder('—')->toggleable(),
                TextColumn::make('status')->badge()
                    ->formatStateUsing(fn (string $state): string => ucwords(str_replace('_', ' ', $state))),
                TextColumn::make('due_at')->label('Due')->dateTime('M j, Y')->sortable()->placeholder('—'),
            ])
            ->headerActions([
                CreateAction::make()->label('Add task'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('due_at');
    }
}
