<?php

namespace Tests\Feature;

use App\Filament\Resources\Entities\Pages\EditEntity;
use App\Models\Entity;
use App\Models\FinanceTransaction;
use App\Models\Organization;
use App\Models\Relationship;
use App\Models\RelationshipType;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class CampaignCommitteeTest extends TestCase
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

    public function test_setup_committee_creates_org_connection_and_links_donations(): void
    {
        $candidate = Entity::create(['entity_type' => 'person', 'display_name' => 'Victor Marx']);

        // TRACER contributions to the candidate's committee (unlinked).
        foreach (['c1' => 250, 'c2' => 500] as $hash => $amt) {
            FinanceTransaction::create([
                'source' => 'tracer', 'data_type' => 'contributions', 'year' => 2024,
                'committee_name' => 'Victor Marx for Governor', 'candidate_name' => 'Victor Marx',
                'contributor_name' => 'Some Donor', 'amount' => $amt,
                'transaction_date' => '2024-06-01', 'row_hash' => $hash,
            ]);
        }

        Livewire::test(EditEntity::class, ['record' => $candidate->getKey()])
            ->callAction('setupCommittee', data: ['committee_name' => 'Victor Marx for Governor'])
            ->assertHasNoActionErrors();

        // Committee organization created, typed as a committee.
        $committee = Entity::where('display_name', 'Victor Marx for Governor')->first();
        $this->assertNotNull($committee);
        $this->assertSame('organization', $committee->entity_type);
        $this->assertSame('committee', $committee->organizationProfile->org_subtype);

        // "Campaign committee" connection: candidate → committee.
        $type = RelationshipType::where('name', 'candidate_committee')->first();
        $this->assertDatabaseHas('relationships', [
            'from_entity_id' => $candidate->id,
            'to_entity_id' => $committee->id,
            'relationship_type_id' => $type->id,
        ]);

        // Both contributions now linked to the committee (received) and attributed to the candidate.
        $this->assertSame(2, FinanceTransaction::where('committee_entity_id', $committee->id)->count());
        $this->assertSame(2, FinanceTransaction::where('candidate_entity_id', $candidate->id)->count());
    }
}
