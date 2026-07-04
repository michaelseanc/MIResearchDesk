<?php

namespace Tests\Feature;

use App\Filament\Resources\Entities\Pages\ListEntities;
use App\Models\Entity;
use App\Models\Organization;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class EntityOriginTest extends TestCase
{
    use RefreshDatabase;

    private Entity $curated;
    private Entity $financeActor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $owner = User::where('email', 'owner@monumentindependent.com')->first();
        $this->actingAs($owner);
        Organization::useOrganization(1);
        app(PermissionRegistrar::class)->setPermissionsTeamId(1);

        $this->curated = Entity::create(['entity_type' => 'person', 'display_name' => 'Curated Subject']);
        $this->financeActor = Entity::create([
            'entity_type' => 'person', 'display_name' => 'Imported Donor', 'origin' => 'finance_import',
        ]);
    }

    public function test_finance_actors_hidden_from_list_by_default(): void
    {
        Livewire::test(ListEntities::class)
            ->assertCanSeeTableRecords([$this->curated])
            ->assertCanNotSeeTableRecords([$this->financeActor]);
    }

    public function test_toggling_filter_reveals_finance_actors(): void
    {
        Livewire::test(ListEntities::class)
            ->removeTableFilter('hide_finance_actors')
            ->assertCanSeeTableRecords([$this->curated, $this->financeActor]);
    }

    public function test_adopt_promotes_finance_actor_into_dossiers(): void
    {
        Livewire::test(ListEntities::class)
            ->removeTableFilter('hide_finance_actors')
            ->callAction(TestAction::make('adopt')->table($this->financeActor))
            ->assertHasNoActionErrors();

        $this->assertNull($this->financeActor->fresh()->origin);
    }
}
