<?php

namespace Tests\Feature;

use App\Filament\Resources\Entities\Pages\EditEntity;
use App\Filament\Resources\Entities\RelationManagers\AddressesRelationManager;
use App\Filament\Resources\Entities\RelationManagers\ContactMethodsRelationManager;
use App\Models\Entity;
use App\Models\Organization;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ContactInfoTest extends TestCase
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

        $this->entity = Entity::create(['entity_type' => 'person', 'display_name' => 'Contact Test']);
    }

    public function test_can_add_contact_method(): void
    {
        Livewire::test(ContactMethodsRelationManager::class, [
            'ownerRecord' => $this->entity,
            'pageClass' => EditEntity::class,
        ])
            ->assertOk()
            ->callAction(TestAction::make('create')->table(), data: [
                'method' => 'email',
                'value' => 'jane@example.com',
                'is_preferred' => true,
                'sensitivity' => 'internal',
            ])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('contact_methods', [
            'entity_id' => $this->entity->id,
            'method' => 'email',
            'value' => 'jane@example.com',
            'organization_id' => 1,
        ]);
    }

    public function test_can_add_structured_address(): void
    {
        Livewire::test(AddressesRelationManager::class, [
            'ownerRecord' => $this->entity,
            'pageClass' => EditEntity::class,
        ])
            ->assertOk()
            ->callAction(TestAction::make('create')->table(), data: [
                'label' => 'home',
                'is_primary' => true,
                'line1' => '123 Second St',
                'city' => 'Monument',
                'state' => 'CO',
                'postal_code' => '80132',
                'country' => 'US',
                'sensitivity' => 'internal',
            ])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('addresses', [
            'entity_id' => $this->entity->id,
            'city' => 'Monument',
            'postal_code' => '80132',
            'organization_id' => 1,
        ]);
    }
}
