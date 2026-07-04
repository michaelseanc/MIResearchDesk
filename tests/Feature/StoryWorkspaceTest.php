<?php

namespace Tests\Feature;

use App\Filament\Resources\Stories\Pages\CreateStory;
use App\Filament\Resources\Stories\Pages\ListStories;
use App\Filament\Resources\Stories\RelationManagers\EntitiesRelationManager;
use App\Filament\Resources\Stories\Pages\EditStory;
use App\Models\Entity;
use App\Models\Organization;
use App\Models\Story;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class StoryWorkspaceTest extends TestCase
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

    public function test_can_create_story(): void
    {
        Livewire::test(ListStories::class)->assertOk();

        Livewire::test(CreateStory::class)
            ->fillForm([
                'title' => 'Buc-ee’s rezoning',
                'type' => 'investigation',
                'status' => 'reporting',
                'priority' => 'high',
                'central_question' => 'Who benefits from the rezoning?',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('stories', [
            'title' => 'Buc-ee’s rezoning',
            'type' => 'investigation',
            'organization_id' => 1,
        ]);
    }

    public function test_can_attach_entity_to_story_with_role(): void
    {
        $story = Story::create(['title' => 'Water rights', 'type' => 'ongoing_issue', 'status' => 'reporting']);
        $person = Entity::create(['entity_type' => 'person', 'display_name' => 'District Manager']);

        Livewire::test(EntitiesRelationManager::class, [
            'ownerRecord' => $story,
            'pageClass' => EditStory::class,
        ])
            ->assertOk()
            ->callAction(TestAction::make('attach')->table(), data: [
                'recordId' => $person->id,
                'role_note' => 'Key official',
            ])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('story_entities', [
            'story_id' => $story->id,
            'entity_id' => $person->id,
            'role_note' => 'Key official',
        ]);
    }
}
