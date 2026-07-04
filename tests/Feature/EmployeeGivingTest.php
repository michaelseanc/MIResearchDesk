<?php

namespace Tests\Feature;

use App\Filament\Resources\Entities\Pages\EditEntity;
use App\Filament\Resources\Entities\RelationManagers\EmployeeGivingRelationManager;
use App\Models\Entity;
use App\Models\FinanceTransaction;
use App\Models\Organization;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class EmployeeGivingTest extends TestCase
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

    private function contribution(string $donor, string $employer, string $hash): FinanceTransaction
    {
        return FinanceTransaction::create([
            'source' => 'tracer', 'data_type' => 'contributions', 'year' => 2024,
            'committee_name' => 'Some Committee', 'contributor_name' => $donor, 'employer' => $employer,
            'amount' => 100, 'transaction_date' => '2024-06-01', 'row_hash' => $hash,
        ]);
    }

    public function test_panel_matches_employer_variants_but_not_others(): void
    {
        $org = Entity::create(['entity_type' => 'organization', 'display_name' => 'The Platinum Group']);

        $a = $this->contribution('Ryan Graham', 'THE PLATINUM GROUP', 'e1');
        $b = $this->contribution('Nicole Graham', 'THE PLATINUM GROUP, REALTORS', 'e2');
        $c = $this->contribution('Someone Else', 'PLATINUM GROUP', 'e3');
        $unrelated = $this->contribution('Adam Dill', 'PLATINUM HOME SALES', 'e4'); // no "group" token
        $offtopic = $this->contribution('Jane Doe', 'ACME CORP', 'e5');

        Livewire::test(EmployeeGivingRelationManager::class, [
            'ownerRecord' => $org,
            'pageClass' => EditEntity::class,
        ])
            ->assertCanSeeTableRecords([$a, $b, $c])
            ->assertCanNotSeeTableRecords([$unrelated, $offtopic]);
    }

    public function test_panel_only_visible_for_organizations(): void
    {
        $org = Entity::create(['entity_type' => 'organization', 'display_name' => 'Acme Group']);
        $person = Entity::create(['entity_type' => 'person', 'display_name' => 'Jane Doe']);

        $this->assertTrue(EmployeeGivingRelationManager::canViewForRecord($org, EditEntity::class));
        $this->assertFalse(EmployeeGivingRelationManager::canViewForRecord($person, EditEntity::class));
    }
}
