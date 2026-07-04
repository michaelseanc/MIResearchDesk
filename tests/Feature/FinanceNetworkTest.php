<?php

namespace Tests\Feature;

use App\Filament\Resources\Entities\Pages\EditEntity;
use App\Models\Entity;
use App\Models\FinanceTransaction;
use App\Models\Organization;
use App\Models\Relationship;
use App\Models\RelationshipType;
use App\Models\User;
use App\Services\Finance\FinanceNetworkBuilder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class FinanceNetworkTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $owner = User::where('email', 'owner@monumentindependent.com')->first();
        $this->actingAs($owner);
        Organization::useOrganization(1);
        app(PermissionRegistrar::class)->setPermissionsTeamId(1);

        $rows = [
            // donor, type, amount, hash
            ['John Q Public', 'Individual', 600, 'hx1'],
            ['John Q Public', 'Individual', 600, 'hx2'],   // same donor, 2nd gift
            ['Small Giver', 'Individual', 500, 'hy'],      // below threshold
            ['Acme Development LLC', 'Business', 2000, 'hz'],
        ];
        foreach ($rows as [$donor, $type, $amount, $hash]) {
            FinanceTransaction::create([
                'source' => 'tracer', 'data_type' => 'contributions', 'year' => 2024,
                'committee_name' => 'Friends of Monument', 'contributor_name' => $donor,
                'contributor_type' => $type, 'amount' => $amount, 'transaction_date' => '2024-06-01',
                'row_hash' => $hash,
            ]);
        }
    }

    public function test_builds_committee_network_above_threshold(): void
    {
        $result = app(FinanceNetworkBuilder::class)->buildFromCommittee('Friends of Monument', 1000, 100);

        $this->assertSame(2, $result['donors_promoted'], 'only John and Acme cleared $1000');
        $this->assertSame(2, $result['connections']);

        // Committee is an organization entity.
        $committee = Entity::where('display_name', 'Friends of Monument')->first();
        $this->assertNotNull($committee);
        $this->assertSame('organization', $committee->entity_type);

        // Donors created with correct types; sub-threshold donor NOT created.
        $this->assertSame('person', Entity::where('display_name', 'John Q Public')->value('entity_type'));
        $this->assertSame('organization', Entity::where('display_name', 'Acme Development LLC')->value('entity_type'));
        $this->assertNull(Entity::where('display_name', 'Small Giver')->first(), 'sub-threshold donor not promoted');

        // One aggregated edge per donor (John gave twice → still one edge).
        $donated = RelationshipType::where('name', 'donated_to')->first();
        $this->assertSame(2, Relationship::where('to_entity_id', $committee->id)->where('relationship_type_id', $donated->id)->count());

        $john = Entity::where('display_name', 'John Q Public')->first();
        $edge = Relationship::where('from_entity_id', $john->id)->where('to_entity_id', $committee->id)->first();
        $this->assertStringContainsString('$1,200.00 across 2 contributions', $edge->notes);

        // John's transactions are linked + approved; the committee link is set on all rows.
        $this->assertSame(2, FinanceTransaction::where('contributor_name', 'John Q Public')->where('match_state', 'approved')->count());
        $this->assertSame(4, FinanceTransaction::whereNotNull('committee_entity_id')->count());
    }

    public function test_is_idempotent(): void
    {
        $builder = app(FinanceNetworkBuilder::class);
        $builder->buildFromCommittee('Friends of Monument', 1000, 100);
        $builder->buildFromCommittee('Friends of Monument', 1000, 100);

        $committee = Entity::where('display_name', 'Friends of Monument')->first();
        // No duplicate committee entities, no duplicate edges.
        $this->assertSame(1, Entity::where('display_name', 'Friends of Monument')->count());
        $this->assertSame(2, Relationship::where('to_entity_id', $committee->id)->count());
    }

    public function test_build_all_respects_committee_threshold(): void
    {
        // A second, tiny committee that should NOT clear a $10k committee floor.
        FinanceTransaction::create([
            'source' => 'tracer', 'data_type' => 'contributions', 'year' => 2024,
            'committee_name' => 'Tiny Committee', 'contributor_name' => 'Minor Donor',
            'contributor_type' => 'Individual', 'amount' => 500, 'transaction_date' => '2024-06-01',
            'row_hash' => 'tiny1',
        ]);

        // Friends of Monument total = 600+600+500+2000 = 3700 (below 10k too), so raise nothing clears
        // unless we lower the floor. Use a floor that only the seeded committee ($3,700) clears.
        $result = app(FinanceNetworkBuilder::class)->buildAllCommittees(
            minCommitteeTotal: 1000,
            minDonorTotal: 1000,
            maxDonorsPerCommittee: 50,
        );

        // Friends of Monument ($3,700) qualifies; Tiny Committee ($500) does not.
        $this->assertSame(1, $result['committees']);
        $this->assertNotNull(Entity::where('display_name', 'Friends of Monument')->first());
        $this->assertNull(Entity::where('display_name', 'Tiny Committee')->first());
    }

    public function test_build_ignores_expenditures_and_loans(): void
    {
        // A vendor the committee PAID and a lender — must not be mistaken for donors.
        FinanceTransaction::create([
            'source' => 'tracer', 'data_type' => 'expenditures', 'year' => 2024,
            'committee_name' => 'Friends of Monument', 'contributor_name' => 'Sketchy Vendor LLC',
            'contributor_type' => 'Business', 'amount' => 5000, 'transaction_date' => '2024-07-01', 'row_hash' => 'exp1',
        ]);
        FinanceTransaction::create([
            'source' => 'tracer', 'data_type' => 'loans', 'year' => 2024,
            'committee_name' => 'Friends of Monument', 'contributor_name' => 'Big Bank',
            'contributor_type' => 'Business', 'amount' => 9000, 'transaction_date' => '2024-01-01', 'row_hash' => 'loan1',
        ]);

        // "Every donor" — still only the three real contributors become edges.
        $result = app(FinanceNetworkBuilder::class)->buildFromCommittee('Friends of Monument', 0, 100);

        $this->assertSame(3, $result['connections'], 'only contribution donors form edges');
        $this->assertNull(Entity::where('display_name', 'Sketchy Vendor LLC')->first(), 'expenditure payee not promoted');
        $this->assertNull(Entity::where('display_name', 'Big Bank')->first(), 'loan lender not promoted');
    }

    public function test_import_auto_builds_networks_for_contributions(): void
    {
        $batch = \App\Services\Finance\TracerImporter::createBatch(1, 'contributions', 2024);

        // Two $6,000 donors → committee clears the $10k auto-build floor.
        foreach ([['Major Donor A', 'ha'], ['Major Donor B', 'hb']] as [$donor, $hash]) {
            FinanceTransaction::create([
                'source' => 'tracer', 'data_type' => 'contributions', 'year' => 2024,
                'import_batch_id' => $batch->id, 'committee_name' => 'Big PAC',
                'contributor_name' => $donor, 'contributor_type' => 'Individual',
                'amount' => 6000, 'transaction_date' => '2024-06-01', 'row_hash' => $hash,
            ]);
        }

        (new \App\Services\Finance\TracerImporter())->buildNetworks($batch);

        $batch->refresh();
        $this->assertSame(1, $batch->network_committees);
        $this->assertSame(2, $batch->network_connections);

        $committee = Entity::where('display_name', 'Big PAC')->first();
        $this->assertNotNull($committee);
        $this->assertSame(2, Relationship::where('to_entity_id', $committee->id)->count());
    }

    public function test_import_network_build_respects_org_configured_thresholds(): void
    {
        // Lower this org's committee floor so a small local committee ($300) auto-builds.
        $org = Organization::withoutGlobalScopes()->find(1);
        $settings = $org->settings ?? [];
        $settings['finance_network'] = ['min_committee_total' => 100, 'min_donor_total' => 0, 'max_donors_per_committee' => 50];
        $org->update(['settings' => $settings]);

        $batch = \App\Services\Finance\TracerImporter::createBatch(1, 'contributions', 2024);
        FinanceTransaction::create([
            'source' => 'tracer', 'data_type' => 'contributions', 'year' => 2024,
            'import_batch_id' => $batch->id, 'committee_name' => 'Small Local Committee',
            'contributor_name' => 'Tiny Donor', 'contributor_type' => 'Individual',
            'amount' => 300, 'transaction_date' => '2024-06-01', 'row_hash' => 'sl1',
        ]);

        (new \App\Services\Finance\TracerImporter())->buildNetworks($batch);

        $batch->refresh();
        $this->assertSame(1, $batch->network_committees, 'small committee builds under the lowered floor');
        $this->assertSame(1, $batch->network_connections);
        $this->assertNotNull(Entity::where('display_name', 'Small Local Committee')->first());
    }

    public function test_import_network_build_skips_expenditure_batches(): void
    {
        $batch = \App\Services\Finance\TracerImporter::createBatch(1, 'expenditures', 2024);
        FinanceTransaction::create([
            'source' => 'tracer', 'data_type' => 'expenditures', 'year' => 2024,
            'import_batch_id' => $batch->id, 'committee_name' => 'Big PAC',
            'contributor_name' => 'Vendor', 'amount' => 50000, 'transaction_date' => '2024-06-01', 'row_hash' => 'v1',
        ]);

        (new \App\Services\Finance\TracerImporter())->buildNetworks($batch);

        $batch->refresh();
        $this->assertNull($batch->network_committees, 'expenditures do not trigger a donor-network build');
        $this->assertNull(Entity::where('display_name', 'Big PAC')->first());
    }

    public function test_build_donor_network_button_on_committee_dossier(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $committee = Entity::create([
            'entity_type' => 'organization',
            'display_name' => 'Friends of Monument',
            'sensitivity' => 'internal',
        ]);

        // "Every donor" (min_total 0) — even the $500 giver becomes a connection.
        Livewire::test(EditEntity::class, ['record' => $committee->getRouteKey()])
            ->callAction('buildDonorNetwork', ['min_total' => '0']);

        $this->assertSame(3, Relationship::where('to_entity_id', $committee->id)->count(), 'all three distinct donors connected');
        $this->assertNotNull(Entity::where('display_name', 'Small Giver')->first(), 'sub-threshold donor promoted when "every donor" chosen');
        $this->assertSame(
            4,
            FinanceTransaction::where('committee_name', 'Friends of Monument')->whereNotNull('contributor_entity_id')->count(),
            'all contributions linked to a donor entity',
        );
    }
}
