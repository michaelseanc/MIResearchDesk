<?php

namespace Tests\Feature;

use App\Filament\Resources\Entities\EntityResource;
use App\Filament\Resources\Entities\Pages\CreateEntity;
use App\Filament\Resources\Entities\Pages\ListEntities;
use App\Models\Entity;
use App\Models\Organization;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class EntityResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    private function actAsOwner(): User
    {
        $owner = User::where('email', 'owner@monumentindependent.com')->first();
        $this->actingAs($owner);
        Organization::useOrganization($owner->organization_id);
        app(PermissionRegistrar::class)->setPermissionsTeamId($owner->organization_id);

        return $owner;
    }

    public function test_owner_can_render_entity_list(): void
    {
        $this->actAsOwner();

        Livewire::test(ListEntities::class)->assertOk();
    }

    public function test_owner_can_create_person_with_profile(): void
    {
        $this->actAsOwner();

        Livewire::test(CreateEntity::class)
            ->fillForm([
                'entity_type' => 'person',
                'status' => 'active',
                'display_name' => 'Jane Q. Official',
                'personProfile' => [
                    'professional_role' => 'Town Trustee',
                    'source_status' => 'official',
                ],
                'sensitivity' => 'internal',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $entity = Entity::where('display_name', 'Jane Q. Official')->first();
        $this->assertNotNull($entity, 'Entity was created');
        $this->assertSame(1, $entity->organization_id, 'Entity stamped with tenant');
        $this->assertSame('Town Trustee', $entity->personProfile?->professional_role, 'Profile saved via relationship');
    }

    public function test_sealed_entity_hidden_from_user_without_permission(): void
    {
        // A sealed record created in the tenant.
        $this->actAsOwner();
        Entity::create([
            'entity_type' => 'person',
            'display_name' => 'Confidential Source #14',
            'sensitivity' => 'sealed',
        ]);

        // A reporter has no view_confidential_identity capability.
        $reporter = User::create([
            'organization_id' => 1,
            'name' => 'Staff Reporter',
            'email' => 'reporter@monumentindependent.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $reporter->assignRole('Reporter');

        $this->actingAs($reporter);
        app(PermissionRegistrar::class)->setPermissionsTeamId(1);

        $names = EntityResource::getEloquentQuery()->pluck('display_name');

        $this->assertFalse($reporter->can('view_confidential_identity'), 'Reporter lacks the capability');
        $this->assertTrue($names->doesntContain('Confidential Source #14'), 'Sealed record hidden from reporter');
    }
}
