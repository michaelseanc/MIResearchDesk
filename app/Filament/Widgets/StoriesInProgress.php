<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Stories\StoryResource;
use App\Models\Story;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class StoriesInProgress extends TableWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected function getTableHeading(): string
    {
        return 'Stories in progress';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => Story::query()->whereNotIn('status', ['published', 'archived']))
            ->defaultSort('updated_at', 'desc')
            ->paginated([5, 10, 25])
            ->columns([
                TextColumn::make('title')->searchable()->weight('medium')->wrap(),
                TextColumn::make('status')->badge()
                    ->formatStateUsing(fn (string $state): string => ucwords(str_replace('_', ' ', $state)))
                    ->color(fn (string $state): string => match ($state) {
                        'legal_review' => 'danger', 'edit', 'draft' => 'info', default => 'warning',
                    }),
                TextColumn::make('priority')->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'urgent' => 'danger', 'high' => 'warning', default => 'gray',
                    }),
                TextColumn::make('next_action')->label('Next action')->limit(60)->placeholder('—')->wrap(),
                TextColumn::make('updated_at')->label('Updated')->since()->sortable(),
            ])
            ->recordUrl(fn (Story $record): string => StoryResource::getUrl('edit', ['record' => $record]));
    }
}
