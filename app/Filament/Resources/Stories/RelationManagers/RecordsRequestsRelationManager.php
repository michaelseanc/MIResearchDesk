<?php

namespace App\Filament\Resources\Stories\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Public-records requests tied to this story, with submission and due dates for follow-up.
 */
class RecordsRequestsRelationManager extends RelationManager
{
    protected static string $relationship = 'recordsRequests';

    protected static ?string $title = 'Records requests';

    protected static string|\BackedEnum|null $icon = 'heroicon-o-inbox-arrow-down';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('agency')->required()->maxLength(255),
            Select::make('status')
                ->options([
                    'draft' => 'Draft', 'submitted' => 'Submitted', 'acknowledged' => 'Acknowledged',
                    'partial' => 'Partial', 'fulfilled' => 'Fulfilled', 'denied' => 'Denied', 'appealed' => 'Appealed',
                ])->default('draft')->required(),
            Textarea::make('subject')->required()->rows(2)->columnSpanFull(),
            DatePicker::make('submitted_at')->label('Submitted'),
            DatePicker::make('due_at')->label('Response due'),
            Textarea::make('response_note')->rows(2)->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('agency')
            ->columns([
                TextColumn::make('agency')->searchable()->weight('medium'),
                TextColumn::make('subject')->limit(50)->wrap(),
                TextColumn::make('status')->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'fulfilled' => 'success', 'denied' => 'danger', 'appealed' => 'warning', 'draft' => 'gray', default => 'info',
                    }),
                TextColumn::make('due_at')->label('Due')->date()->sortable()->placeholder('—'),
            ])
            ->headerActions([
                CreateAction::make()->label('Add request'),
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
