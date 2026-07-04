<?php

namespace Tests\Feature;

use App\Filament\Resources\Expenditures\Pages\ListExpenditures;
use App\Filament\Resources\FinanceTransactions\Pages\ListFinanceTransactions;
use App\Filament\Resources\Loans\Pages\ListLoans;
use App\Models\FinanceTransaction;
use App\Models\Organization;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class FinanceResourceScopingTest extends TestCase
{
    use RefreshDatabase;

    private FinanceTransaction $contribution;
    private FinanceTransaction $expenditure;
    private FinanceTransaction $loan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $owner = User::where('email', 'owner@monumentindependent.com')->first();
        $this->actingAs($owner);
        Organization::useOrganization(1);
        app(PermissionRegistrar::class)->setPermissionsTeamId(1);

        $this->contribution = FinanceTransaction::create([
            'source' => 'tracer', 'data_type' => 'contributions', 'year' => 2024,
            'committee_name' => 'Committee A', 'contributor_name' => 'Donor Contribution',
            'amount' => 100, 'transaction_date' => '2024-06-01', 'row_hash' => 'c1',
        ]);
        $this->expenditure = FinanceTransaction::create([
            'source' => 'tracer', 'data_type' => 'expenditures', 'year' => 2024,
            'committee_name' => 'Committee A', 'contributor_name' => 'Vendor Expenditure',
            'amount' => 200, 'transaction_date' => '2024-06-02', 'row_hash' => 'e1',
        ]);
        $this->loan = FinanceTransaction::create([
            'source' => 'tracer', 'data_type' => 'loans', 'year' => 2024,
            'committee_name' => 'Committee A', 'contributor_name' => 'Lender Loan',
            'amount' => 5000, 'transaction_date' => '2024-06-03', 'row_hash' => 'l1',
            'source_extra' => ['loan_balance' => '4200.00', 'interest_rate' => '3.5'],
        ]);
    }

    public function test_contributions_list_shows_only_contributions(): void
    {
        Livewire::test(ListFinanceTransactions::class)
            ->assertCanSeeTableRecords([$this->contribution])
            ->assertCanNotSeeTableRecords([$this->expenditure, $this->loan]);
    }

    public function test_expenditures_list_shows_only_expenditures(): void
    {
        Livewire::test(ListExpenditures::class)
            ->assertCanSeeTableRecords([$this->expenditure])
            ->assertCanNotSeeTableRecords([$this->contribution, $this->loan]);
    }

    public function test_loans_list_shows_only_loans_and_renders_balance(): void
    {
        Livewire::test(ListLoans::class)
            ->assertCanSeeTableRecords([$this->loan])
            ->assertCanNotSeeTableRecords([$this->contribution, $this->expenditure])
            ->assertSee('4,200.00'); // outstanding balance from source_extra
    }
}
