<?php

namespace Tests\Feature;

use App\Models\Entity;
use App\Models\FinanceTransaction;
use App\Models\Organization;
use App\Models\User;
use App\Services\Finance\TracerImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class TracerImportTest extends TestCase
{
    use RefreshDatabase;

    private string $fixture;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $owner = User::where('email', 'owner@monumentindependent.com')->first();
        $this->actingAs($owner);
        Organization::useOrganization(1);
        app(PermissionRegistrar::class)->setPermissionsTeamId(1);

        $this->fixture = base_path('tests/Fixtures/tracer_contributions_sample.csv');
    }

    public function test_filtered_import_parses_dedupes_and_counts(): void
    {
        $importer = new TracerImporter();
        $batch = TracerImporter::createBatch(1, 'contributions', 2026, [
            'terms' => ['Monument', 'Tri-View'],
        ]);

        $importer->parseRows($this->fixture, $batch);

        $batch->refresh();
        $this->assertSame(3, $batch->rows_total, 'saw all 3 rows');
        $this->assertSame(2, $batch->rows_imported, 'kept the two tracked rows');
        $this->assertSame(1, $batch->rows_skipped, 'skipped the Denver row');
        $this->assertSame(2, FinanceTransaction::count());

        // $-and-comma amount parsed to a number.
        $monument = FinanceTransaction::where('committee_name', 'Friends of Monument')->first();
        $this->assertEquals(1500.00, (float) $monument->amount);
        $this->assertSame('2026-06-15', $monument->transaction_date->toDateString());

        // Re-import is idempotent (dedupe by row_hash).
        $importer->parseRows($this->fixture, $batch);
        $this->assertSame(2, FinanceTransaction::count(), 're-import created no duplicates');
    }

    public function test_auto_match_links_committee_to_existing_entity(): void
    {
        $triview = Entity::create([
            'entity_type' => 'organization',
            'display_name' => 'Tri-View Metropolitan District',
        ]);

        $importer = new TracerImporter();
        $batch = TracerImporter::createBatch(1, 'contributions', 2026, ['terms' => ['Monument', 'Tri-View']]);
        $importer->parseRows($this->fixture, $batch);
        $importer->autoMatch($batch);

        $txn = FinanceTransaction::where('committee_name', 'Tri-View Metropolitan District')->first();
        $this->assertSame($triview->id, $txn->committee_entity_id, 'committee auto-matched to entity');
        $this->assertSame('auto', $txn->match_state);

        $unmatched = FinanceTransaction::where('committee_name', 'Friends of Monument')->first();
        $this->assertNull($unmatched->committee_entity_id);
        $this->assertSame('unmatched', $unmatched->match_state);
    }

    public function test_no_filter_imports_everything(): void
    {
        $importer = new TracerImporter();
        $batch = TracerImporter::createBatch(1, 'contributions', 2026, []);
        $importer->parseRows($this->fixture, $batch);

        $this->assertSame(3, $batch->fresh()->rows_imported);
    }

    public function test_expenditures_parse_with_the_same_pipeline(): void
    {
        $importer = new TracerImporter();
        $batch = TracerImporter::createBatch(1, 'expenditures', 2026, ['terms' => ['Monument']]);
        $importer->parseRows(base_path('tests/Fixtures/tracer_expenditures_sample.csv'), $batch);

        $batch->refresh();
        $this->assertSame(3, $batch->rows_total);
        $this->assertSame(2, $batch->rows_imported, 'kept the two Monument-committee payments');
        $this->assertSame(1, $batch->rows_skipped, 'skipped the Denver committee');

        // ExpenditureAmount / ExpenditureDate mapped; payee (org) recomposed from LastName.
        $mailers = FinanceTransaction::where('data_type', 'expenditures')
            ->where('contributor_name', 'ACME PRINTING LLC')->first();
        $this->assertNotNull($mailers);
        $this->assertEquals(2500.50, (float) $mailers->amount);
        $this->assertSame('2026-03-10', $mailers->transaction_date->toDateString());
        $this->assertSame('Friends of Monument', $mailers->committee_name);
        $this->assertSame('Advertising', $mailers->txn_subtype);
        $this->assertSame('Monetary (Itemized)', $mailers->source_extra['disbursement_type']);

        // A split personal payee name recomposes across parts (verbatim, so source case is kept).
        $consultant = FinanceTransaction::where('contributor_name', 'ROBERT J SMITH')->first();
        $this->assertNotNull($consultant, 'FirstName/MI/LastName recomposed');

        // Jurisdiction (county) is captured and persisted.
        $this->assertSame('EL PASO', $mailers->jurisdiction);
    }

    public function test_county_filter_keeps_matching_jurisdictions(): void
    {
        $importer = new TracerImporter();
        // Mixed case on purpose — matching is case-insensitive against TRACER's uppercase form.
        $batch = TracerImporter::createBatch(1, 'expenditures', 2026, ['counties' => ['El Paso']]);
        $importer->parseRows(base_path('tests/Fixtures/tracer_expenditures_sample.csv'), $batch);

        $batch->refresh();
        $this->assertSame(2, $batch->rows_imported, 'kept the two El Paso rows');
        $this->assertSame(1, $batch->rows_skipped, 'skipped the Denver-county row');
        $this->assertSame(0, FinanceTransaction::where('jurisdiction', 'DENVER')->count());
        $this->assertSame(2, FinanceTransaction::where('jurisdiction', 'EL PASO')->count());
    }

    public function test_loans_parse_with_single_name_and_capture_balance(): void
    {
        $importer = new TracerImporter();
        $batch = TracerImporter::createBatch(1, 'loans', 2026, ['terms' => ['Monument']]);
        $importer->parseRows(base_path('tests/Fixtures/tracer_loans_sample.csv'), $batch);

        $batch->refresh();
        $this->assertSame(2, $batch->rows_total);
        $this->assertSame(1, $batch->rows_imported, 'kept the Monument loan');
        $this->assertSame(1, $batch->rows_skipped, 'skipped the Denver loan');

        $loan = FinanceTransaction::where('data_type', 'loans')->first();
        $this->assertNotNull($loan);
        // Lender comes from the single "Name" column (no First/Last split in loan files).
        $this->assertSame('JANE CANDIDATE', $loan->contributor_name);
        // Principal + date come from LoanAmount / LoanDate, not PaymentAmount / PaymentDate.
        $this->assertEquals(10000.00, (float) $loan->amount);
        $this->assertSame('2026-01-05', $loan->transaction_date->toDateString());
        $this->assertSame('Candidate', $loan->txn_subtype);
        // Loan-specific fields preserved in source_extra.
        $this->assertEquals('8500.00', $loan->source_extra['loan_balance']);
        $this->assertEquals('3.5', $loan->source_extra['interest_rate']);
    }
}
