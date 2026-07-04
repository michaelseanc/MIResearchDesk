<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Entities\EntityResource;
use App\Models\ContactInteraction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class FollowUpsDue extends TableWidget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    protected function getTableHeading(): string
    {
        return 'Follow-ups due (next 7 days)';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => ContactInteraction::query()
                ->whereNotNull('follow_up_at')
                ->where('follow_up_at', '<=', now()->addDays(7))
                ->when(
                    ! auth()->user()?->can('view_confidential_identity'),
                    fn (Builder $q) => $q->where('visibility', '!=', 'sealed'),
                ))
            ->defaultSort('follow_up_at', 'asc')
            ->paginated([5, 10])
            ->emptyStateHeading('No follow-ups due')
            ->emptyStateDescription('Contact-log entries with a follow-up date appear here as they approach.')
            ->columns([
                TextColumn::make('entity.display_name')->label('Who')->weight('medium')->searchable(),
                TextColumn::make('interaction_type')->label('Type')->badge()
                    ->formatStateUsing(fn (string $state): string => ucwords(str_replace('_', ' ', $state))),
                TextColumn::make('summary')->label('Notes')->limit(60)->wrap()->placeholder('—'),
                TextColumn::make('follow_up_at')->label('Follow up')->dateTime('M j, Y')->sortable()
                    ->color(fn ($record): string => $record->follow_up_at?->isPast() ? 'danger' : 'warning'),
            ])
            ->recordUrl(fn (ContactInteraction $record): ?string => $record->entity_id
                ? EntityResource::getUrl('edit', ['record' => $record->entity_id])
                : null);
    }
}
