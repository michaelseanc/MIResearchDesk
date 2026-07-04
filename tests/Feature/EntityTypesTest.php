<?php

namespace Tests\Feature;

use App\Filament\Resources\Entities\Pages\CreateEntity;
use App\Filament\Resources\Entities\Pages\EditEntity;
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

class EntityTypesTest extends TestCase
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

    public function test_can_create_news_entity_using_organization_fields(): void
    {
        Livewire::test(CreateEntity::class)
            ->fillForm([
                'entity_type' => 'news',
                'display_name' => 'The Monument Independent',
                'status' => 'active',
                'sensitivity' => 'internal',
                'organizationProfile' => ['org_subtype' => 'media', 'website' => 'https://monumentindependent.com'],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $entity = Entity::where('display_name', 'The Monument Independent')->first();
        $this->assertSame('news', $entity->entity_type);
        $this->assertTrue($entity->isOrganizationLike());
        $this->assertSame('media', $entity->organizationProfile->org_subtype);
    }

    public function test_entity_type_is_editable_on_existing_record(): void
    {
        $person = Entity::create(['entity_type' => 'person', 'display_name' => 'Reclassify Me']);

        Livewire::test(EditEntity::class, ['record' => $person->getKey()])
            ->fillForm(['entity_type' => 'government'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('government', $person->fresh()->entity_type);
    }

    public function test_org_like_types_render_as_org_on_graph(): void
    {
        $person = Entity::create(['entity_type' => 'person', 'display_name' => 'Reporter']);
        $pac = Entity::create(['entity_type' => 'pac', 'display_name' => 'Growth PAC']);
        Relationship::create([
            'from_entity_id' => $person->id, 'to_entity_id' => $pac->id,
            'relationship_type_id' => RelationshipType::where('name', 'donated_to')->first()->id,
            'verification_state' => 'reported',
        ]);

        $graph = app(GraphBuilder::class)->build($person->id, 1);
        $pacNode = collect($graph['nodes'])->firstWhere('data.label', 'Growth PAC');

        $this->assertSame('org', $pacNode['data']['kind']);
        $this->assertSame('PAC', $pacNode['data']['typeLabel']);
    }
}
