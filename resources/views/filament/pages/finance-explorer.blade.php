<x-filament-panels::page>
    @php
        $money = fn ($v) => '$' . number_format((float) $v, 0);
        $th = 'px-4 py-2 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400';
        $thNum = $th . ' text-right';
        $td = 'px-4 py-2.5 border-t border-gray-100 dark:border-gray-800 align-top';
        $tdNum = $td . ' text-right tabular-nums whitespace-nowrap';
    @endphp

    <x-filament::section>
        <label class="flex max-w-xs flex-col gap-1 text-sm">
            <span class="font-medium text-gray-700 dark:text-gray-300">Election / reporting year</span>
            <select wire:model.live="year"
                class="fi-input rounded-lg border-gray-300 text-sm dark:bg-gray-800 dark:border-gray-600">
                <option value="">All years</option>
                @foreach ($this->getYearOptions() as $y => $label)
                    <option value="{{ $y }}">{{ $label }}</option>
                @endforeach
            </select>
        </label>
    </x-filament::section>

    <x-filament::section heading="Top donors" description="Largest contributors {{ $year ? 'in ' . $year : 'across all years' }}">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <colgroup>
                    <col style="width: 52%"><col style="width: 18%"><col style="width: 12%"><col style="width: 18%">
                </colgroup>
                <thead><tr>
                    <th class="{{ $th }}">Contributor</th>
                    <th class="{{ $thNum }}">Total</th>
                    <th class="{{ $thNum }}">Gifts</th>
                    <th class="{{ $thNum }}"></th>
                </tr></thead>
                <tbody>
                    @forelse ($this->topDonors() as $d)
                        <tr>
                            <td class="{{ $td }} font-medium">{{ $d->contributor_name }}</td>
                            <td class="{{ $tdNum }}">{{ $money($d->total) }}</td>
                            <td class="{{ $tdNum }} text-gray-500">{{ number_format($d->n) }}</td>
                            <td class="{{ $tdNum }}">
                                <button type="button"
                                    wire:click="showDonor(@js($d->contributor_name))"
                                    x-on:click="setTimeout(() => document.getElementById('donor-history')?.scrollIntoView({ behavior: 'smooth', block: 'start' }), 250)"
                                    class="text-primary-600 hover:underline">History</button>
                            </td>
                        </tr>
                    @empty
                        <tr><td class="{{ $td }} text-gray-500" colspan="4">No data.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>

    <x-filament::section heading="Top recipient committees" description="Where El Paso County money went {{ $year ? 'in ' . $year : '' }}">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                {{-- Widths match the Top donors table above so Total/Gifts line up (last col is empty spacer). --}}
                <colgroup>
                    <col style="width: 52%"><col style="width: 18%"><col style="width: 12%"><col style="width: 18%">
                </colgroup>
                <thead><tr>
                    <th class="{{ $th }}">Committee</th>
                    <th class="{{ $thNum }}">Total</th>
                    <th class="{{ $thNum }}">Gifts</th>
                    <th class="{{ $th }}"></th>
                </tr></thead>
                <tbody>
                    @forelse ($this->topCommittees() as $c)
                        <tr>
                            <td class="{{ $td }} font-medium">{{ $c->committee_name }}</td>
                            <td class="{{ $tdNum }}">{{ $money($c->total) }}</td>
                            <td class="{{ $tdNum }} text-gray-500">{{ number_format($c->n) }}</td>
                            <td class="{{ $td }}"></td>
                        </tr>
                    @empty
                        <tr><td class="{{ $td }} text-gray-500" colspan="4">No data.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>

    <div id="donor-history">
        <x-filament::section heading="Donor giving history"
            description="Click “History” on any top donor, or type a name below.">
            <label class="mb-3 flex max-w-md flex-col gap-1 text-sm">
                <span class="font-medium text-gray-700 dark:text-gray-300">Donor</span>
                <input type="text" wire:model.live.debounce.500ms="donorName"
                    placeholder="Exact contributor name"
                    class="fi-input rounded-lg border-gray-300 text-sm dark:bg-gray-800 dark:border-gray-600" />
            </label>

            @if ($donorName)
                <p class="mb-3 text-sm">
                    <span class="font-semibold">{{ $donorName }}</span> —
                    total given: <span class="font-semibold tabular-nums">{{ $money($this->donorTotal()) }}</span>
                    <button type="button" wire:click="$set('donorName', null)" class="ml-2 text-xs text-gray-400 hover:underline">clear</button>
                </p>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <colgroup>
                            <col style="width: 14%"><col style="width: 38%"><col style="width: 16%"><col style="width: 18%"><col style="width: 14%">
                        </colgroup>
                        <thead><tr>
                            <th class="{{ $th }}">Date</th>
                            <th class="{{ $th }}">Committee</th>
                            <th class="{{ $th }}">City</th>
                            <th class="{{ $th }}">Employer</th>
                            <th class="{{ $thNum }}">Amount</th>
                        </tr></thead>
                        <tbody>
                            @forelse ($this->donorHistory() as $t)
                                <tr>
                                    <td class="{{ $td }} whitespace-nowrap">{{ $t->transaction_date?->format('M j, Y') ?? '—' }}</td>
                                    <td class="{{ $td }}">{{ $t->committee_name }}</td>
                                    <td class="{{ $td }} text-gray-500">{{ $t->city }}</td>
                                    <td class="{{ $td }} text-gray-500">{{ $t->employer ?: '—' }}</td>
                                    <td class="{{ $tdNum }}">{{ $money($t->amount) }}</td>
                                </tr>
                            @empty
                                <tr><td class="{{ $td }} text-gray-500" colspan="5">No contributions found for that exact name.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            @endif
        </x-filament::section>
    </div>

    <x-filament::section heading="Shared donors between two committees"
        description="Who gave to both? A fast way to spot aligned money.">
        <div class="mb-3 grid grid-cols-1 gap-4 md:grid-cols-2">
            <label class="flex flex-col gap-1 text-sm">
                <span class="font-medium text-gray-700 dark:text-gray-300">Committee A</span>
                <select wire:model.live="committeeA"
                    class="fi-input rounded-lg border-gray-300 text-sm dark:bg-gray-800 dark:border-gray-600">
                    <option value="">— select —</option>
                    @foreach ($this->getCommitteeOptions() as $c)
                        <option value="{{ $c }}">{{ $c }}</option>
                    @endforeach
                </select>
            </label>
            <label class="flex flex-col gap-1 text-sm">
                <span class="font-medium text-gray-700 dark:text-gray-300">Committee B</span>
                <select wire:model.live="committeeB"
                    class="fi-input rounded-lg border-gray-300 text-sm dark:bg-gray-800 dark:border-gray-600">
                    <option value="">— select —</option>
                    @foreach ($this->getCommitteeOptions() as $c)
                        <option value="{{ $c }}">{{ $c }}</option>
                    @endforeach
                </select>
            </label>
        </div>

        @if ($committeeA && $committeeB)
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <colgroup>
                        <col style="width: 50%"><col style="width: 25%"><col style="width: 25%">
                    </colgroup>
                    <thead><tr>
                        <th class="{{ $th }}">Contributor</th>
                        <th class="{{ $thNum }}">To A</th>
                        <th class="{{ $thNum }}">To B</th>
                    </tr></thead>
                    <tbody>
                        @forelse ($this->sharedDonors() as $s)
                            <tr>
                                <td class="{{ $td }} font-medium">{{ $s->contributor_name }}</td>
                                <td class="{{ $tdNum }}">{{ $money($s->to_a) }}</td>
                                <td class="{{ $tdNum }}">{{ $money($s->to_b) }}</td>
                            </tr>
                        @empty
                            <tr><td class="{{ $td }} text-gray-500" colspan="3">No donors gave to both.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>

    {{-- ============================ SPENDING (expenditures) ============================ --}}
    <x-filament::section heading="Top spending committees"
        description="Where committees' money went {{ $year ? 'in ' . $year : 'across all years' }} (expenditures)">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <colgroup><col style="width: 60%"><col style="width: 22%"><col style="width: 18%"></colgroup>
                <thead><tr>
                    <th class="{{ $th }}">Committee</th>
                    <th class="{{ $thNum }}">Total spent</th>
                    <th class="{{ $thNum }}">Payments</th>
                </tr></thead>
                <tbody>
                    @forelse ($this->topSpendingCommittees() as $c)
                        <tr>
                            <td class="{{ $td }} font-medium">{{ $c->committee_name }}</td>
                            <td class="{{ $tdNum }}">{{ $money($c->total) }}</td>
                            <td class="{{ $tdNum }} text-gray-500">{{ number_format($c->n) }}</td>
                        </tr>
                    @empty
                        <tr><td class="{{ $td }} text-gray-500" colspan="3">No expenditure data imported yet. Use “Import from TRACER” → Expenditures.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>

    <x-filament::section heading="Top payees &amp; vendors"
        description="Who received the most committee money {{ $year ? 'in ' . $year : '' }} (expenditures)">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <colgroup><col style="width: 60%"><col style="width: 22%"><col style="width: 18%"></colgroup>
                <thead><tr>
                    <th class="{{ $th }}">Payee / vendor</th>
                    <th class="{{ $thNum }}">Total received</th>
                    <th class="{{ $thNum }}">Payments</th>
                </tr></thead>
                <tbody>
                    @forelse ($this->topPayees() as $p)
                        <tr>
                            <td class="{{ $td }} font-medium">{{ $p->contributor_name }}</td>
                            <td class="{{ $tdNum }}">{{ $money($p->total) }}</td>
                            <td class="{{ $tdNum }} text-gray-500">{{ number_format($p->n) }}</td>
                        </tr>
                    @empty
                        <tr><td class="{{ $td }} text-gray-500" colspan="3">No expenditure data imported yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>

    {{-- ================================ LOANS ================================ --}}
    <x-filament::section heading="Loans"
        description="Money loaned to committees {{ $year ? 'in ' . $year : '' }} — self-loans and outstanding balances are often the story.">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <colgroup>
                    <col style="width: 30%"><col style="width: 26%"><col style="width: 14%"><col style="width: 12%"><col style="width: 12%"><col style="width: 6%">
                </colgroup>
                <thead><tr>
                    <th class="{{ $th }}">Committee (borrower)</th>
                    <th class="{{ $th }}">Lender</th>
                    <th class="{{ $thNum }}">Loan amount</th>
                    <th class="{{ $th }}">Date</th>
                    <th class="{{ $thNum }}">Balance</th>
                    <th class="{{ $thNum }}">Rate</th>
                </tr></thead>
                <tbody>
                    @forelse ($this->loans() as $l)
                        <tr>
                            <td class="{{ $td }} font-medium">{{ $l->committee_name }}</td>
                            <td class="{{ $td }}">{{ $l->contributor_name }}</td>
                            <td class="{{ $tdNum }}">{{ $money($l->amount) }}</td>
                            <td class="{{ $td }} whitespace-nowrap">{{ $l->transaction_date?->format('M j, Y') ?? '—' }}</td>
                            <td class="{{ $tdNum }}">
                                @php $bal = $l->source_extra['loan_balance'] ?? null; @endphp
                                {{ $bal !== null ? $money($bal) : '—' }}
                            </td>
                            <td class="{{ $tdNum }} text-gray-500">
                                @php $rate = $l->source_extra['interest_rate'] ?? null; @endphp
                                {{ $rate !== null ? rtrim(rtrim(number_format((float) $rate, 2), '0'), '.') . '%' : '—' }}
                            </td>
                        </tr>
                    @empty
                        <tr><td class="{{ $td }} text-gray-500" colspan="6">No loan data imported yet. Use “Import from TRACER” → Loans.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-panels::page>
