<?php

namespace Tests\Feature;

use App\Filament\Resources\Entities\Pages\EditEntity;
use App\Filament\Resources\Entities\RelationManagers\DonationsReceivedRelationManager;
use App\Models\Entity;
use App\Models\FinanceTransaction;
use App\Models\Organization;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class DonationsReceivedRenderTest extends TestCase
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

    public function test_received_table_shows_committee_linked_rows(): void
    {
        $committee = Entity::create(['entity_type' => 'election_committee', 'display_name' => 'Victor Marx for Governor']);

        $txn = FinanceTransaction::create([
            'source' => 'tracer', 'data_type' => 'contributions', 'year' => 2024,
            'committee_name' => 'Victor Marx for Governor', 'contributor_name' => 'A Donor',
            'committee_entity_id' => $committee->id, 'match_state' => 'approved',
            'amount' => 500, 'transaction_date' => '2024-06-01', 'row_hash' => 'vm1',
        ]);

        Livewire::test(DonationsReceivedRelationManager::class, [
            'ownerRecord' => $committee,
            'pageClass' => EditEntity::class,
        ])
            ->assertOk()
            ->assertCanSeeTableRecords([$txn]);
    }
}
