<?php

namespace Tests\Feature;

use App\Filament\Pages\FinanceExplorer;
use App\Models\FinanceTransaction;
use App\Models\Organization;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class FinanceExplorerTest extends TestCase
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

        $rows = [
            ['X', 'Committee A', 100, 'h1'],
            ['X', 'Committee B', 200, 'h2'],
            ['Y', 'Committee A', 50, 'h3'],
        ];
        foreach ($rows as [$donor, $committee, $amount, $hash]) {
            FinanceTransaction::create([
                'source' => 'tracer', 'data_type' => 'contributions', 'year' => 2024,
                'contributor_name' => $donor, 'committee_name' => $committee,
                'amount' => $amount, 'transaction_date' => '2024-06-01', 'row_hash' => $hash,
            ]);
        }
    }

    public function test_top_donors_aggregate_by_contributor(): void
    {
        $page = new FinanceExplorer();
        $donors = $page->topDonors();

        $this->assertSame('X', $donors->first()->contributor_name);
        $this->assertEquals(300, (float) $donors->first()->total);
        $this->assertEquals(2, (int) $donors->first()->n);
    }

    public function test_donor_history_and_total(): void
    {
        $page = new FinanceExplorer();
        $page->donorName = 'X';

        $this->assertCount(2, $page->donorHistory());
        $this->assertEquals(300, $page->donorTotal());
    }

    public function test_shared_donors_between_two_committees(): void
    {
        $page = new FinanceExplorer();
        $page->committeeA = 'Committee A';
        $page->committeeB = 'Committee B';

        $shared = $page->sharedDonors();

        // Only X gave to both; Y gave to A only.
        $this->assertCount(1, $shared);
        $this->assertSame('X', $shared->first()->contributor_name);
        $this->assertEquals(100, (float) $shared->first()->to_a);
        $this->assertEquals(200, (float) $shared->first()->to_b);
    }

    public function test_page_renders(): void
    {
        Livewire::test(FinanceExplorer::class)->assertOk();
    }

    public function test_history_button_sets_donor_and_shows_rows(): void
    {
        Livewire::test(FinanceExplorer::class)
            ->call('showDonor', 'X')
            ->assertSet('donorName', 'X')
            ->assertSee('300'); // X's total ($100 + $200) rendered in the history section
    }

    public function test_expenditure_and_loan_rows_are_isolated_from_contribution_views(): void
    {
        // An expenditure and a loan sharing the same table must not leak into contribution totals.
        FinanceTransaction::create([
            'source' => 'tracer', 'data_type' => 'expenditures', 'year' => 2024,
            'contributor_name' => 'Acme Vendor', 'committee_name' => 'Committee A',
            'amount' => 9999, 'transaction_date' => '2024-05-01', 'row_hash' => 'e1',
        ]);
        FinanceTransaction::create([
            'source' => 'tracer', 'data_type' => 'loans', 'year' => 2024,
            'contributor_name' => 'Jane Lender', 'committee_name' => 'Committee A',
            'amount' => 5000, 'transaction_date' => '2024-01-01', 'row_hash' => 'l1',
            'source_extra' => ['loan_balance' => '4000', 'interest_rate' => '3.5'],
        ]);

        $page = new FinanceExplorer();

        // Top donor is still X at $300 — the $9,999 expenditure payee is excluded.
        $this->assertSame('X', $page->topDonors()->first()->contributor_name);
        $this->assertEquals(300, (float) $page->topDonors()->first()->total);

        // Spending + loan views surface the new rows.
        $this->assertSame('Acme Vendor', $page->topPayees()->first()->contributor_name);
        $this->assertEquals(9999, (float) $page->topSpendingCommittees()->first()->total);

        $loan = $page->loans()->first();
        $this->assertSame('Jane Lender', $loan->contributor_name);
        $this->assertEquals(5000, (float) $loan->amount);
        $this->assertSame('4000', $loan->source_extra['loan_balance']);
    }
}
