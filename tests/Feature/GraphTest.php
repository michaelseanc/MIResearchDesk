<?php

namespace Tests\Feature;

use App\Filament\Pages\RelationshipGraph;
use App\Models\Entity;
use App\Models\Organization;
use App\Models\Relationship;
use App\Models\RelationshipType;
use App\Models\User;
use App\Services\Graph\GraphBuilder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class GraphTest extends TestCase
{
    use RefreshDatabase;

    private Entity $a;
    private Entity $b;
    private Entity $c;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $owner = User::where('email', 'owner@monumentindependent.com')->first();
        $this->actingAs($owner);
        Organization::useOrganization(1);
        app(PermissionRegistrar::class)->setPermissionsTeamId(1);

        // A --donated_to--> B --employed_by--> C
        $this->a = Entity::create(['entity_type' => 'person', 'display_name' => 'Donor A']);
        $this->b = Entity::create(['entity_type' => 'organization', 'display_name' => 'Committee B']);
        $this->c = Entity::create(['entity_type' => 'person', 'display_name' => 'Staffer C']);

        $donated = RelationshipType::where('name', 'donated_to')->first();
        $employed = RelationshipType::where('name', 'employed_by')->first();

        Relationship::create(['from_entity_id' => $this->a->id, 'to_entity_id' => $this->b->id, 'relationship_type_id' => $donated->id, 'verification_state' => 'reported']);
        Relationship::create(['from_entity_id' => $this->c->id, 'to_entity_id' => $this->b->id, 'relationship_type_id' => $employed->id, 'verification_state' => 'lead']);
    }

    public function test_depth_expands_the_neighborhood(): void
    {
        $g = app(GraphBuilder::class);

        $d1 = $g->build($this->a->id, 1);
        $this->assertCount(2, $d1['nodes'], 'A + B at depth 1');
        $this->assertCount(1, $d1['edges']);

        $d2 = $g->build($this->a->id, 2);
        $this->assertCount(3, $d2['nodes'], 'A, B, C at depth 2');
        $this->assertCount(2, $d2['edges']);
    }

    public function test_traverses_both_directions(): void
    {
        // Focus B: A points in (incoming), C points in too — both should appear at depth 1.
        $g = app(GraphBuilder::class)->build($this->b->id, 1);
        $labels = collect($g['nodes'])->pluck('data.label')->all();

        $this->assertContains('Donor A', $labels);
        $this->assertContains('Staffer C', $labels);
    }

    public function test_type_filter_limits_edges(): void
    {
        $donated = RelationshipType::where('name', 'donated_to')->first();

        $g = app(GraphBuilder::class)->build($this->b->id, 2, ['types' => [$donated->id]]);
        $this->assertCount(1, $g['edges'], 'only the donation edge');
    }

    public function test_sealed_entity_hidden_from_unpermitted_user(): void
    {
        $this->c->update(['sensitivity' => 'sealed']);

        $reporter = User::create([
            'organization_id' => 1, 'name' => 'Reporter', 'email' => 'r2@monumentindependent.com',
            'password' => bcrypt('x'), 'is_active' => true,
        ]);
        $reporter->assignRole('Reporter');
        $this->actingAs($reporter);
        app(PermissionRegistrar::class)->setPermissionsTeamId(1);

        $g = app(GraphBuilder::class)->build($this->b->id, 2);
        $labels = collect($g['nodes'])->pluck('data.label')->all();

        $this->assertNotContains('Staffer C', $labels, 'sealed node hidden');
        // The B–C edge must be dropped since an endpoint is hidden.
        $this->assertCount(1, $g['edges']);
    }

    public function test_graph_page_renders_and_builds_data(): void
    {
        Livewire::test(RelationshipGraph::class)
            ->assertOk()
            ->set('focusEntityId', $this->a->id)
            ->set('depth', 2)
            ->assertSet('graph.meta.nodeCount', 3);
    }
}
