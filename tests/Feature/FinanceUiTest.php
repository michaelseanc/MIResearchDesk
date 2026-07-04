<?php

namespace Tests\Feature;

use App\Filament\Resources\FinanceImportBatches\Pages\ListFinanceImportBatches;
use App\Filament\Resources\FinanceTransactions\Pages\ListFinanceTransactions;
use App\Jobs\ImportTracerData;
use App\Models\Entity;
use App\Models\FinanceImportBatch;
use App\Models\FinanceTransaction;
use App\Models\Organization;
use App\Models\Relationship;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class FinanceUiTest extends TestCase
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

    public function test_import_action_queues_batch_and_saves_filter(): void
    {
        Queue::fake();

        Livewire::test(ListFinanceImportBatches::class)
            ->assertOk()
            ->callAction('importTracer', data: [
                'data_type' => 'contributions',
                'year' => 2026,
                'terms' => ['Monument', 'Tri-View'],
                'cities' => [],
                'zips' => [],
            ])
            ->assertHasNoActionErrors();

        $batch = FinanceImportBatch::first();
        $this->assertNotNull($batch);
        $this->assertSame(['Monument', 'Tri-View'], $batch->filter['terms']);
        Queue::assertPushed(ImportTracerData::class);

        // Filter persisted to the org for the weekly auto-refresh.
        $org = Organization::withoutGlobalScopes()->find(1);
        $this->assertSame(['Monument', 'Tri-View'], $org->settings['finance_filter']['terms']);
    }

    public function test_resolve_links_entities_and_records_donation_connection(): void
    {
        $donor = Entity::create(['entity_type' => 'person', 'display_name' => 'John Q Public']);
        $committee = Entity::create(['entity_type' => 'organization', 'display_name' => 'Friends of Monument']);

        $txn = FinanceTransaction::create([
            'source' => 'tracer', 'data_type' => 'contributions', 'year' => 2026,
            'contributor_name' => 'John Q Public', 'committee_name' => 'Friends of Monument',
            'amount' => 1500.00, 'transaction_date' => '2026-06-15', 'file_number' => '1001',
            'row_hash' => 'testhash1',
        ]);

        Livewire::test(ListFinanceTransactions::class)
            ->assertOk()
            ->callAction(TestAction::make('resolve')->table($txn), data: [
                'contributor_entity_id' => $donor->id,
                'committee_entity_id' => $committee->id,
                'create_connection' => true,
            ])
            ->assertHasNoActionErrors();

        $txn->refresh();
        $this->assertSame($donor->id, $txn->contributor_entity_id);
        $this->assertSame('approved', $txn->match_state);

        $this->assertDatabaseHas('relationships', [
            'from_entity_id' => $donor->id,
            'to_entity_id' => $committee->id,
            'verification_state' => 'reported',
        ]);
        $rel = Relationship::first();
        $this->assertStringContainsString('TRACER contribution', $rel->notes);
    }
}
