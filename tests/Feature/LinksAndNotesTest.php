<?php

namespace Tests\Feature;

use App\Filament\Resources\Entities\Pages\EditEntity;
use App\Filament\Resources\Entities\RelationManagers\InteractionsRelationManager;
use App\Filament\Resources\Entities\RelationManagers\LinksRelationManager;
use App\Models\Entity;
use App\Models\Organization;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class LinksAndNotesTest extends TestCase
{
    use RefreshDatabase;

    private Entity $entity;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $owner = User::where('email', 'owner@monumentindependent.com')->first();
        $this->actingAs($owner);
        Organization::useOrganization(1);
        app(PermissionRegistrar::class)->setPermissionsTeamId(1);

        $this->entity = Entity::create(['entity_type' => 'person', 'display_name' => 'Linkable Person']);
    }

    public function test_can_paste_article_link(): void
    {
        Livewire::test(LinksRelationManager::class, [
            'ownerRecord' => $this->entity,
            'pageClass' => EditEntity::class,
        ])
            ->assertOk()
            ->callAction(TestAction::make('create')->table(), data: [
                'kind' => 'article',
                'url' => 'https://monumentindependent.com/some-story',
                'title' => 'Trustee questioned over development vote',
                'published_at' => '2026-06-01',
                'sensitivity' => 'public',
            ])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('links', [
            'entity_id' => $this->entity->id,
            'kind' => 'article',
            'url' => 'https://monumentindependent.com/some-story',
            'organization_id' => 1,
        ]);
    }

    public function test_can_log_a_dated_call_note(): void
    {
        Livewire::test(InteractionsRelationManager::class, [
            'ownerRecord' => $this->entity,
            'pageClass' => EditEntity::class,
        ])
            ->assertOk()
            ->callAction(TestAction::make('create')->table(), data: [
                'interaction_type' => 'call',
                'occurred_at' => '2026-07-02 14:30:00',
                'summary' => 'Called re: rezoning. Confirmed they abstained. Will send minutes.',
                'attribution_terms' => 'on_record',
                'visibility' => 'internal',
            ])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('contact_interactions', [
            'entity_id' => $this->entity->id,
            'interaction_type' => 'call',
            'attribution_terms' => 'on_record',
            'organization_id' => 1,
        ]);
    }
}
