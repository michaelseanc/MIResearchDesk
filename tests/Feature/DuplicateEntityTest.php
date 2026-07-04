<?php

namespace Tests\Feature;

use App\Filament\Resources\Entities\Pages\ListEntities;
use App\Models\Entity;
use App\Models\Organization;
use App\Models\Relationship;
use App\Models\RelationshipType;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class DuplicateEntityTest extends TestCase
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

    public function test_duplicate_copies_shared_context_and_drops_identity(): void
    {
        $company = Entity::create(['entity_type' => 'organization', 'display_name' => 'Tri-View Metro District']);

        $original = Entity::create([
            'entity_type' => 'person',
            'display_name' => 'Alice Manager',
            'photo_path' => 'entity-photos/alice.jpg',
            'primary_geography' => 'Monument',
            'public_summary' => 'The GM.',
        ]);
        $original->personProfile()->create([
            'full_name' => 'Alice Manager',
            'professional_role' => 'General Manager',
            'current_company' => 'Tri-View Metro District',
            'current_company_entity_id' => $company->id,
            'geography_detail' => 'Monument, CO',
        ]);
        // The original has an unrelated connection that must NOT be copied.
        Relationship::create([
            'from_entity_id' => $original->id, 'to_entity_id' => $company->id,
            'relationship_type_id' => RelationshipType::where('name', 'donated_to')->first()->id,
            'verification_state' => 'reported',
        ]);

        Livewire::test(ListEntities::class)
            ->callAction(TestAction::make('duplicate')->table($original), data: ['display_name' => 'Bob Newhire'])
            ->assertHasNoActionErrors();

        $new = Entity::where('display_name', 'Bob Newhire')->first();
        $this->assertNotNull($new);

        // Shared context carried over.
        $this->assertSame('person', $new->entity_type);
        $this->assertSame('Monument', $new->primary_geography);
        $this->assertSame('Tri-View Metro District', $new->personProfile->current_company);
        $this->assertSame($company->id, $new->personProfile->current_company_entity_id);

        // Identity + narrative dropped.
        $this->assertNull($new->photo_path);
        $this->assertNull($new->public_summary);
        $this->assertNull($new->personProfile->full_name);
        $this->assertNull($new->personProfile->professional_role);

        // The new person gets the shared-employer connection (from current_company link) but NOT the
        // original's other connections.
        $this->assertSame(1, Relationship::where('from_entity_id', $new->id)->count());
        $employed = RelationshipType::where('name', 'employed_by')->first();
        $this->assertSame($employed->id, Relationship::where('from_entity_id', $new->id)->first()->relationship_type_id);
    }
}
