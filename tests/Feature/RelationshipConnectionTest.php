<?php

namespace Tests\Feature;

use App\Filament\Resources\Entities\Pages\EditEntity;
use App\Filament\Resources\Entities\RelationManagers\RelationshipsFromRelationManager;
use App\Models\Entity;
use App\Models\Organization;
use App\Models\Relationship;
use App\Models\RelationshipType;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use RuntimeException;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class RelationshipConnectionTest extends TestCase
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

    public function test_connection_links_existing_entity_without_creating_duplicate(): void
    {
        $alice = Entity::create(['entity_type' => 'person', 'display_name' => 'Alice Mayor']);
        $acme = Entity::create(['entity_type' => 'organization', 'display_name' => 'Acme Development LLC']);
        $type = RelationshipType::where('name', 'financial_interest_in')->first();

        $entityCountBefore = Entity::count();

        Livewire::test(RelationshipsFromRelationManager::class, [
            'ownerRecord' => $alice,
            'pageClass' => EditEntity::class,
        ])
            ->assertOk()
            ->callAction(TestAction::make('create')->table(), data: [
                'to_entity_id' => $acme->id,        // selected an EXISTING entity
                'relationship_type_id' => $type->id,
                'status' => 'active',
                'verification_state' => 'reported',
                'sensitivity' => 'internal',
            ])
            ->assertHasNoActionErrors();

        $this->assertSame($entityCountBefore, Entity::count(), 'No duplicate entity was created');
        $this->assertDatabaseHas('relationships', [
            'from_entity_id' => $alice->id,
            'to_entity_id' => $acme->id,
            'relationship_type_id' => $type->id,
            'verification_state' => 'reported',
            'organization_id' => 1,
        ]);
    }

    public function test_incoming_connection_appears_on_target_dossier(): void
    {
        $alice = Entity::create(['entity_type' => 'person', 'display_name' => 'Alice Donor']);
        $acme = Entity::create(['entity_type' => 'organization', 'display_name' => 'Acme Committee']);
        $type = RelationshipType::where('name', 'donated_to')->first();

        $rel = Relationship::create([
            'from_entity_id' => $alice->id,
            'to_entity_id' => $acme->id,
            'relationship_type_id' => $type->id,
            'verification_state' => 'reported',
        ]);

        // Viewing Acme (the target) shows the incoming connection in the combined panel.
        Livewire::test(RelationshipsFromRelationManager::class, [
            'ownerRecord' => $acme,
            'pageClass' => EditEntity::class,
        ])
            ->assertOk()
            ->assertCanSeeTableRecords([$rel]);
    }

    public function test_relationship_cannot_be_verified_without_evidence(): void
    {
        $x = Entity::create(['entity_type' => 'person', 'display_name' => 'X']);
        $y = Entity::create(['entity_type' => 'person', 'display_name' => 'Y']);
        $type = RelationshipType::first();

        $this->expectException(RuntimeException::class);

        Relationship::create([
            'from_entity_id' => $x->id,
            'to_entity_id' => $y->id,
            'relationship_type_id' => $type->id,
            'verification_state' => 'verified', // blocked: no evidence exists
        ]);
    }
}
