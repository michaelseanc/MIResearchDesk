<?php

namespace Tests\Feature;

use App\Models\Entity;
use App\Models\Organization;
use App\Models\PersonProfile;
use App\Models\Relationship;
use App\Models\RelationshipType;
use App\Models\User;
use App\Services\Graph\GraphBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class CurrentCompanyLinkTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $owner = User::where('email', 'owner@monumentindependent.com')->first();
        $this->actingAs($owner);
        Organization::useOrganization(1);
        app(PermissionRegistrar::class)->setPermissionsTeamId(1);
    }

    public function test_linking_company_creates_employed_by_connection_and_appears_on_graph(): void
    {
        $person = Entity::create(['entity_type' => 'person', 'display_name' => 'Dana Duggan']);
        $company = Entity::create(['entity_type' => 'organization', 'display_name' => 'Tri-View Metro District']);

        // Link the person's current company (as the dossier form does).
        PersonProfile::create([
            'entity_id' => $person->id,
            'current_company' => 'Tri-View Metro District',
            'current_company_entity_id' => $company->id,
        ]);

        $type = RelationshipType::where('name', 'employed_by')->first();
        $this->assertDatabaseHas('relationships', [
            'from_entity_id' => $person->id,
            'to_entity_id' => $company->id,
            'relationship_type_id' => $type->id,
            'verification_state' => 'reported',
        ]);

        // The employment edge is now part of the graph around the person.
        $graph = app(GraphBuilder::class)->build($person->id, 1);
        $labels = collect($graph['nodes'])->pluck('data.label')->all();
        $this->assertContains('Tri-View Metro District', $labels);
        $this->assertSame(1, $graph['meta']['edgeCount']);
    }

    public function test_relink_is_idempotent(): void
    {
        $person = Entity::create(['entity_type' => 'person', 'display_name' => 'Person']);
        $company = Entity::create(['entity_type' => 'organization', 'display_name' => 'Company']);

        $profile = PersonProfile::create(['entity_id' => $person->id, 'current_company_entity_id' => $company->id]);
        $profile->update(['professional_role' => 'Director']); // triggers saved again

        $this->assertSame(1, Relationship::where('from_entity_id', $person->id)->where('to_entity_id', $company->id)->count());
    }
}
