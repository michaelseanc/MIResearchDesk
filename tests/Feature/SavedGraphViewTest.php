<?php

namespace Tests\Feature;

use App\Filament\Pages\RelationshipGraph;
use App\Models\Entity;
use App\Models\Organization;
use App\Models\SavedGraphView;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class SavedGraphViewTest extends TestCase
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

    public function test_save_view_action_persists_current_filters(): void
    {
        $entity = Entity::create(['entity_type' => 'person', 'display_name' => 'Jane Doe']);

        Livewire::test(RelationshipGraph::class)
            ->set('focusEntityId', $entity->id)
            ->set('depth', 3)
            ->set('verificationStates', ['verified', 'reported'])
            ->callAction('saveView', ['name' => '2027 county commission']);

        $view = SavedGraphView::where('name', '2027 county commission')->first();

        $this->assertNotNull($view);
        $this->assertSame($entity->id, $view->params['focusEntityId']);
        $this->assertSame(3, $view->params['depth']);
        $this->assertSame(['verified', 'reported'], $view->params['verificationStates']);
        $this->assertSame(1, (int) $view->organization_id);
    }

    public function test_loading_a_view_restores_its_filters(): void
    {
        $entity = Entity::create(['entity_type' => 'person', 'display_name' => 'Jane Doe']);

        $view = SavedGraphView::create([
            'user_id' => auth()->id(),
            'name' => "Buc-ee's network",
            'params' => [
                'focusEntityId' => $entity->id,
                'depth' => 3,
                'types' => [],
                'verificationStates' => ['verified'],
                'issueTagId' => null,
            ],
        ]);

        Livewire::test(RelationshipGraph::class)
            ->set('currentViewId', $view->id)
            ->assertSet('focusEntityId', $entity->id)
            ->assertSet('depth', 3)
            ->assertSet('verificationStates', ['verified']);
    }

    public function test_delete_view_action_removes_it(): void
    {
        $view = SavedGraphView::create([
            'user_id' => auth()->id(),
            'name' => 'Temp',
            'params' => ['depth' => 2],
        ]);

        Livewire::test(RelationshipGraph::class)
            ->set('currentViewId', $view->id)
            ->callAction('deleteView')
            ->assertSet('currentViewId', null);

        $this->assertNull(SavedGraphView::find($view->id));
    }

    public function test_views_are_org_scoped(): void
    {
        SavedGraphView::create(['user_id' => auth()->id(), 'name' => 'Org 1 view', 'params' => []]);

        // A view stamped for a different org must not leak into org 1's picker.
        $otherOrg = Organization::create(['name' => 'Other Newsroom', 'slug' => 'other-newsroom']);
        SavedGraphView::withoutGlobalScopes()->create([
            'organization_id' => $otherOrg->id,
            'user_id' => auth()->id(),
            'name' => 'Other org view',
            'params' => [],
        ]);

        $options = (new RelationshipGraph())->getSavedViewOptions();

        $this->assertContains('Org 1 view', $options);
        $this->assertNotContains('Other org view', $options);
    }
}
