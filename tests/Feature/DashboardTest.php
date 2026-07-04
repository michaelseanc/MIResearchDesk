<?php

namespace Tests\Feature;

use App\Filament\Widgets\FollowUpsDue;
use App\Filament\Widgets\NewsroomStats;
use App\Filament\Widgets\StoriesInProgress;
use App\Models\ContactInteraction;
use App\Models\Entity;
use App\Models\Organization;
use App\Models\Story;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $owner = User::where('email', 'owner@monumentindependent.com')->first();
        $this->actingAs($owner);
        Organization::useOrganization(1);
        app(PermissionRegistrar::class)->setPermissionsTeamId(1);
    }

    public function test_stats_widget_renders(): void
    {
        Entity::create(['entity_type' => 'person', 'display_name' => 'Someone']);
        Livewire::test(NewsroomStats::class)->assertOk();
    }

    public function test_stories_in_progress_widget_lists_active_story(): void
    {
        $story = Story::create(['title' => 'Active investigation', 'type' => 'investigation', 'status' => 'reporting']);
        Story::create(['title' => 'Old one', 'type' => 'story', 'status' => 'archived']);

        Livewire::test(StoriesInProgress::class)
            ->assertOk()
            ->assertCanSeeTableRecords([$story]);
    }

    public function test_follow_ups_widget_shows_upcoming(): void
    {
        $entity = Entity::create(['entity_type' => 'person', 'display_name' => 'Callback Person']);
        $due = ContactInteraction::create([
            'entity_id' => $entity->id,
            'interaction_type' => 'call',
            'occurred_at' => now()->subDay(),
            'summary' => 'Promised documents',
            'follow_up_at' => now()->addDays(2),
            'visibility' => 'internal',
        ]);

        Livewire::test(FollowUpsDue::class)
            ->assertOk()
            ->assertCanSeeTableRecords([$due]);
    }
}
