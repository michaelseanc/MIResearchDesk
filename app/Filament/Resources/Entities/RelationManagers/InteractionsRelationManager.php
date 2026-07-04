<?php

namespace App\Filament\Resources\Entities\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Timestamped contact log for an entity: log a call/interview/tip as it happens and take notes
 * that live on the record, each dated and tagged with attribution terms. Sealed entries are
 * hidden from users without confidential-identity access.
 */
class InteractionsRelationManager extends RelationManager
{
    protected static string $relationship = 'interactions';

    protected static ?string $title = 'Contact log & notes';

    protected static string|\BackedEnum|null $icon = 'heroicon-o-chat-bubble-left-right';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('interaction_type')
                ->label('Type')
                ->options([
                    'call' => 'Call',
                    'email' => 'Email',
                    'meeting' => 'Meeting',
                    'tip' => 'Tip',
                    'interview' => 'Interview',
                    'public_comment' => 'Public comment',
                    'records_request' => 'Records request',
                ])->default('call')->required()->native(false),
            DateTimePicker::make('occurred_at')
                ->label('When')
                ->default(now())->required()->seconds(false),
            Textarea::make('summary')
                ->label('Notes')
                ->rows(5)->columnSpanFull()
                ->placeholder('Notes from this contact — what was said, agreed, promised, or requested.'),
            Select::make('attribution_terms')
                ->options([
                    'on_record' => 'On the record',
                    'background' => 'Background',
                    'deep_background' => 'Deep background',
                    'off_the_record' => 'Off the record',
                    'confidential' => 'Confidential',
                ])->native(false)->placeholder('Not specified'),
            DateTimePicker::make('follow_up_at')->label('Follow up on')->seconds(false),
            Select::make('story_id')
                ->label('Related story (optional)')
                ->relationship('story', 'title')
                ->searchable()->preload(),
            Select::make('visibility')
                ->options([
                    'internal' => 'Internal',
                    'sealed' => 'Sealed (restricted)',
                ])->default('internal')->required()
                ->helperText('Sealed entries are hidden from staff without confidential-identity access.'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('summary')
            ->modifyQueryUsing(function (Builder $query): Builder {
                if (! auth()->user()?->can('view_confidential_identity')) {
                    $query->where('visibility', '!=', 'sealed');
                }

                return $query;
            })
            ->columns([
                TextColumn::make('occurred_at')->label('When')->dateTime('M j, Y g:i A')->sortable(),
                TextColumn::make('interaction_type')->label('Type')->badge()
                    ->formatStateUsing(fn (string $state): string => ucfirst(str_replace('_', ' ', $state))),
                TextColumn::make('summary')->label('Notes')->limit(70)->wrap(),
                TextColumn::make('attribution_terms')->label('Terms')->badge()->toggleable()->placeholder('—')
                    ->formatStateUsing(fn (?string $state): string => $state ? ucfirst(str_replace('_', ' ', $state)) : '—'),
                TextColumn::make('follow_up_at')->label('Follow up')->date()->toggleable()->placeholder('—'),
            ])
            ->headerActions([
                CreateAction::make()->label('Log contact'),
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
            ->defaultSort('occurred_at', 'desc');
    }
}
