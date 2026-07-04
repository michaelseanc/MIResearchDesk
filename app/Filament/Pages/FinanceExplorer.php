<?php

namespace App\Filament\Pages;

use App\Models\FinanceTransaction;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;

/**
 * Aggregate "follow the money" views over imported TRACER data: top donors, top recipient
 * committees, a single donor's giving history, and donors shared between two committees. All
 * queries run through the tenant-scoped model, so totals stay within the newsroom's dataset.
 */
class FinanceExplorer extends Page
{
    protected string $view = 'filament.pages.finance-explorer';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?string $navigationLabel = 'Finance explorer';

    protected static string|\UnitEnum|null $navigationGroup = 'Campaign finance';

    protected static ?int $navigationSort = 0;

    public ?int $year = null;
    public ?string $donorName = null;
    public ?string $committeeA = null;
    public ?string $committeeB = null;

    /** @return array<int, string> */
    public function getYearOptions(): array
    {
        return FinanceTransaction::query()
            ->distinct()->orderByDesc('year')->pluck('year', 'year')
            ->mapWithKeys(fn ($y) => [$y => (string) $y])->all();
    }

    /** @return array<string, string> */
    public function getCommitteeOptions(): array
    {
        return FinanceTransaction::query()
            ->whereNotNull('committee_name')->where('committee_name', '!=', '')
            ->distinct()->orderBy('committee_name')->limit(2000)
            ->pluck('committee_name', 'committee_name')->all();
    }

    public function topDonors(): Collection
    {
        return FinanceTransaction::query()
            ->where('data_type', 'contributions')
            ->when($this->year, fn ($q) => $q->where('year', $this->year))
            ->whereNotNull('contributor_name')->where('contributor_name', '!=', '')
            ->selectRaw('contributor_name, SUM(amount) as total, COUNT(*) as n')
            ->groupBy('contributor_name')
            ->orderByDesc('total')
            ->limit(50)->get();
    }

    public function topCommittees(): Collection
    {
        return FinanceTransaction::query()
            ->where('data_type', 'contributions')
            ->when($this->year, fn ($q) => $q->where('year', $this->year))
            ->whereNotNull('committee_name')->where('committee_name', '!=', '')
            ->selectRaw('committee_name, SUM(amount) as total, COUNT(*) as n')
            ->groupBy('committee_name')
            ->orderByDesc('total')
            ->limit(50)->get();
    }

    /** Committees that spent the most (expenditures out). */
    public function topSpendingCommittees(): Collection
    {
        return FinanceTransaction::query()
            ->where('data_type', 'expenditures')
            ->when($this->year, fn ($q) => $q->where('year', $this->year))
            ->whereNotNull('committee_name')->where('committee_name', '!=', '')
            ->selectRaw('committee_name, SUM(amount) as total, COUNT(*) as n')
            ->groupBy('committee_name')
            ->orderByDesc('total')
            ->limit(50)->get();
    }

    /** Vendors / payees that received the most committee money (expenditures). */
    public function topPayees(): Collection
    {
        return FinanceTransaction::query()
            ->where('data_type', 'expenditures')
            ->when($this->year, fn ($q) => $q->where('year', $this->year))
            ->whereNotNull('contributor_name')->where('contributor_name', '!=', '')
            ->selectRaw('contributor_name, SUM(amount) as total, COUNT(*) as n')
            ->groupBy('contributor_name')
            ->orderByDesc('total')
            ->limit(50)->get();
    }

    /** Individual loans (largest first). Balance / interest live in source_extra. */
    public function loans(): Collection
    {
        return FinanceTransaction::query()
            ->where('data_type', 'loans')
            ->when($this->year, fn ($q) => $q->where('year', $this->year))
            ->orderByDesc('amount')
            ->limit(100)->get();
    }

    public function donorHistory(): Collection
    {
        if (! $this->donorName) {
            return collect();
        }

        return FinanceTransaction::query()
            ->where('data_type', 'contributions')
            ->where('contributor_name', $this->donorName)
            ->orderByDesc('transaction_date')
            ->limit(300)->get();
    }

    public function donorTotal(): float
    {
        if (! $this->donorName) {
            return 0.0;
        }

        return (float) FinanceTransaction::query()
            ->where('data_type', 'contributions')
            ->where('contributor_name', $this->donorName)
            ->sum('amount');
    }

    public function sharedDonors(): Collection
    {
        if (! $this->committeeA || ! $this->committeeB || $this->committeeA === $this->committeeB) {
            return collect();
        }

        return FinanceTransaction::query()
            ->where('data_type', 'contributions')
            ->whereIn('committee_name', [$this->committeeA, $this->committeeB])
            ->whereNotNull('contributor_name')->where('contributor_name', '!=', '')
            ->selectRaw(
                'contributor_name,
                 SUM(CASE WHEN committee_name = ? THEN amount ELSE 0 END) as to_a,
                 SUM(CASE WHEN committee_name = ? THEN amount ELSE 0 END) as to_b',
                [$this->committeeA, $this->committeeB],
            )
            ->groupBy('contributor_name')
            ->havingRaw('COUNT(DISTINCT committee_name) = 2')
            ->orderByDesc('to_a')
            ->limit(200)->get();
    }

    public function showDonor(string $name): void
    {
        $this->donorName = $name;
    }
}
