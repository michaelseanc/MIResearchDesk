<?php

namespace App\Filament\Widgets;

use App\Models\Document;
use App\Models\Entity;
use App\Models\Relationship;
use App\Models\Story;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class NewsroomStats extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $activeStories = Story::whereNotIn('status', ['published', 'archived'])->count();
        $unverified = Relationship::whereNotIn('verification_state', ['verified', 'disproven'])->count();

        return [
            Stat::make('People', (string) Entity::query()->people()->count())
                ->description('Individuals tracked')
                ->icon('heroicon-o-user')
                ->color('primary'),
            Stat::make('Organizations', (string) Entity::query()->organizations()->count())
                ->description('Orgs, committees, agencies')
                ->icon('heroicon-o-building-office-2')
                ->color('primary'),
            Stat::make('Active stories', (string) $activeStories)
                ->description('In progress, not yet published/archived')
                ->icon('heroicon-o-newspaper')
                ->color('warning'),
            Stat::make('Connections to verify', (string) $unverified)
                ->description('Leads, reported & corroborated — not yet verified')
                ->icon('heroicon-o-share')
                ->color($unverified > 0 ? 'danger' : 'success'),
            Stat::make('Documents', (string) Document::count())
                ->description('In the evidence library')
                ->icon('heroicon-o-document-text')
                ->color('gray'),
        ];
    }
}
