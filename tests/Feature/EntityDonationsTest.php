<?php

namespace Tests\Feature;

use App\Filament\Resources\Entities\Pages\EditEntity;
use App\Filament\Resources\Entities\RelationManagers\DonationsMadeRelationManager;
use App\Filament\Resources\Entities\RelationManagers\DonationsReceivedRelationManager;
use App\Models\Entity;
use App\Models\FinanceTransaction;
use App\Models\Organization;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class EntityDonationsTest extends TestCase
{
    use RefreshDatabase;

    private Entity $ryan;
    private Entity $committee;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $owner = User::where('email', 'owner@monumentindependent.com')->first();
        $this->actingAs($owner);
        Organization::useOrganization(1);
        app(PermissionRegistrar::class)->setPermissionsTeamId(1);

        $this->ryan = Entity::create(['entity_type' => 'person', 'display_name' => 'Ryan Graham']);
        $this->committee = Entity::create(['entity_type' => 'pac', 'display_name' => 'Friends of Ryan']);

        $rows = [
            // contributor, committee, amount, hash  (all unlinked at first)
            ['Ryan Graham', 'Some Committee', 500, 'h1'],
            ['Ryan A Graham', 'Another Committee', 250, 'h2'],
            ['Someone Else', 'Some Committee', 100, 'h3'],
            ['Big Donor', 'Friends of Ryan', 1000, 'h4'],
        ];
        foreach ($rows as [$donor, $committee, $amount, $hash]) {
            FinanceTransaction::create([
                'source' => 'tracer', 'data_type' => 'contributions', 'year' => 2024,
                'contributor_name' => $donor, 'committee_name' => $committee,
                'amount' => $amount, 'transaction_date' => '2024-06-01', 'row_hash' => $hash,
            ]);
        }
    }

    public function test_match_links_donations_made_by_selected_names(): void
    {
        Livewire::test(DonationsMadeRelationManager::class, [
            'ownerRecord' => $this->ryan,
            'pageClass' => EditEntity::class,
        ])
            ->assertOk()
            // No data passed — relies on the pre-selected suggestions (name-token matches).
            ->callAction(TestAction::make('matchDonor')->table())
            ->assertHasNoActionErrors();

        $this->assertSame(2, $this->ryan->donationsMade()->count());
        $this->assertSame(2, FinanceTransaction::where('contributor_entity_id', $this->ryan->id)->count());
        // The unrelated donor was not touched.
        $this->assertNull(FinanceTransaction::where('contributor_name', 'Someone Else')->first()->contributor_entity_id);
    }

    public function test_match_links_donations_received_by_committee_name(): void
    {
        Livewire::test(DonationsReceivedRelationManager::class, [
            'ownerRecord' => $this->committee,
            'pageClass' => EditEntity::class,
        ])
            ->assertOk()
            // No data passed — relies on the pre-selected suggestions.
            ->callAction(TestAction::make('matchRecipient')->table())
            ->assertHasNoActionErrors();

        $received = FinanceTransaction::where('committee_entity_id', $this->committee->id)->get();
        $this->assertCount(1, $received);
        $this->assertSame('Big Donor', $received->first()->contributor_name);
    }
}
