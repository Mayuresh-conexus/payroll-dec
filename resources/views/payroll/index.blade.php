@extends('layouts.app')
@section('title', 'Weekly payroll')
@section('page_title', 'Weekly payroll')
@section('content')

    <div x-data="payrollPage()" x-init="init(window.payrollServerRows)" class="space-y-6">

        {{-- Filter bar --}}
        <form method="get" action="{{ route('payroll.index') }}"
            class="bg-white rounded-xl shadow-sm border border-slate-200 p-4 flex flex-wrap items-center gap-4 text-sm">
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Year</label>
                <select name="year"
                    class="rounded-lg border-slate-200 text-sm focus:ring-slate-500 focus:border-slate-500">
                    @for ($y = now()->year - 2; $y <= now()->year + 10; $y++)
                        <option value="{{ $y }}" @selected($y == $year)>{{ $y }}</option>
                    @endfor
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Week</label>
                <select name="week"
                    class="rounded-lg border-slate-200 text-sm focus:ring-slate-500 focus:border-slate-500">
                    @for ($w = 1; $w <= $weeksInYear; $w++)
                        <option value="{{ $w }}" @selected($w == $week)>
                            Week {{ $w }}
                        </option>
                    @endfor
                </select>
            </div>

            <div class="flex items-end gap-3">
                <button type="submit"
                    class="px-4 py-2 rounded-lg bg-slate-900 text-white text-sm font-medium hover:bg-slate-800">
                    Load payroll
                </button>

                @if ($run)
                    <a href="{{ route('payroll.exportWeekCsv', ['year' => $year, 'week' => $week]) }}"
                        class="px-4 py-2 rounded-lg border border-slate-300 text-sm font-medium text-slate-700 hover:bg-slate-50">
                        Export XLSX
                    </a>
                @endif

                <a href="{{ route('payroll.weekly.report', ['year' => $year, 'week' => $week]) }}"
                    class="inline-flex items-center px-4 py-2 rounded-lg border border-slate-200 text-sm font-medium text-slate-700 hover:bg-slate-50">
                    View attendance report
                </a>
            </div>

            <div class="ml-auto text-xs text-slate-500">
                Week {{ $week }} / {{ $year }}
            </div>
        </form>

        {{-- Payroll table --}}
        <form action="{{ route('payroll.saveWeek') }}" method="post"
            class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
            @csrf
            <input type="hidden" name="year" value="{{ $year }}">
            <input type="hidden" name="week" value="{{ $week }}">

            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-slate-500 uppercase text-xs font-semibold">
                        <tr>
                            <th class="px-4 py-3 text-left">Code</th>
                            <th class="px-4 py-3 text-left">Name</th>
                            <th class="px-4 py-3 text-center">Type</th>
                            <th class="px-4 py-3 text-center">Attendance</th>
                            <th class="px-4 py-3 text-right">Weekly Total</th>
                            {{-- <th class="px-4 py-3 text-right">Overtime</th> --}}
                            {{-- <th class="px-4 py-3 text-right">Gross salary</th> --}}
                            <th class="px-4 py-3 text-right">Weekly Cash</th>
                            <th class="px-4 py-3 text-right">Weekly Bank</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-slate-100">
                        @forelse($rows as $index => $row)
                            @php
                                $emp = $row['employee'];
                            @endphp

                            <tr class="hover:bg-slate-50/80">
                                <td class="px-4 py-3 font-mono text-xs text-slate-600">
                                    {{ $emp->employee_code }}
                                </td>

                                <td class="px-4 py-3 text-sm font-medium text-slate-800">
                                    {{ $emp->name }}
                                </td>

                                <td class="px-4 py-3 text-center text-xs">
                                    @if ($row['type'] === 'daily_rate')
                                        <span class="inline-flex px-2 py-1 rounded-full bg-emerald-50 text-emerald-700">
                                            Daily
                                        </span>
                                    @else
                                        <span class="inline-flex px-2 py-1 rounded-full bg-blue-50 text-blue-700">
                                            Hourly
                                        </span>
                                    @endif
                                </td>

                                {{-- Attendance (keep your current attendance display logic) --}}
                                <td class="px-4 py-3 text-center text-xs text-slate-600">
                                    @if ($row['type'] === 'daily_rate')
                                        @if (!empty($row['sun_present']))
                                            {{ $row['present_days'] - 1 }}/{{ $row['total_days'] }} days
                                            + <span
                                                class="inline-flex items-center ml-1 px-2 py-0.5 rounded-full bg-orange-50 text-orange-700 text-xs font-semibold">Sun</span>
                                        @else
                                            {{ $row['present_days'] }}/{{ $row['total_days'] }} days
                                        @endif

                                        @if (!empty($row['overtime_amount']))
                                            + <span
                                                class="inline-flex items-center ml-2 px-2 py-0.5 rounded-full bg-purple-50 text-purple-700 text-xs font-semibold">
                                                {{ number_format($row['overtime_amount'], 2) }} OT
                                            </span>
                                        @endif
                                    @else
                                        @if (!empty($row['sun_present']))
                                            {{ $row['total_hours'] - $row['sun_hours'] }} hrs
                                            @if ($row['overtime_hours'])
                                                + <span
                                                    class="inline-flex items-center ml-1 px-2 py-0.5 rounded-full bg-purple-50 text-purple-700 text-xs font-semibold">
                                                    {{ $row['overtime_hours'] }} hrs OT
                                                </span>
                                            @endif
                                            + <span
                                                class="inline-flex items-center ml-1 px-2 py-0.5 rounded-full bg-orange-50 text-orange-700 text-xs font-semibold">
                                                {{ min(6, $row['sun_hours'] ?? 0) }} hrs Sun
                                            </span>
                                        @else
                                            {{ $row['total_hours'] }} hrs
                                            @if ($row['overtime_hours'])
                                                + {{ $row['overtime_hours'] }} OT
                                            @endif
                                        @endif
                                    @endif
                                </td>

                                {{-- Weekly --}}
                                <td class="px-4 py-3 text-right text-sm text-slate-800">
                                    <span x-text="formatMoney(items[{{ $index }}].weekly_amount)"></span>
                                </td>

                                {{-- Addons: show total + breakdown, popup to set cash=true by date 
                                <td class="px-4 py-3 text-right text-sm">
                                    <template x-if="(items[{{ $index }}].addonTotal || 0) > 0">
                                        <div>
                                            <button type="button" @click="items[{{ $index }}].modalOpen = true"
                                                class="text-sm underline">
                                                <span x-text="formatMoney(items[{{ $index }}].addonTotal)"></span>
                                            </button>

                                            <div class="mt-1 text-xs text-slate-600">
                                                Cash:
                                                <strong
                                                    x-text="formatMoney(items[{{ $index }}].addonCashTotal)"></strong>,
                                                Bank:
                                                <strong
                                                    x-text="formatMoney(items[{{ $index }}].addonBankTotal)"></strong>
                                            </div>

                                            <div x-show="items[{{ $index }}].modalOpen" x-cloak
                                                class="fixed inset-0 bg-black/30 z-40 flex items-center justify-center">
                                                <div @click.away="items[{{ $index }}].modalOpen = false"
                                                    class="bg-white rounded-lg p-4 w-96 shadow-lg">
                                                    <div class="flex items-center justify-between mb-3">
                                                        <h3 class="font-semibold">Select addon dates to pay by cash</h3>
                                                        <button type="button"
                                                            @click="items[{{ $index }}].modalOpen = false"
                                                            class="text-sm text-slate-500">Close</button>
                                                    </div>

                                                    <div class="text-xs text-slate-700">
                                                        <ul class="divide-y">
                                                            <template
                                                                x-for="(ad, adIndex) in (items[{{ $index }}].addons || [])"
                                                                :key="adIndex">
                                                                <li class="py-2 flex items-center justify-between">
                                                                    <div>
                                                                        <div class="font-medium" x-text="ad.date || 'n/a'">
                                                                        </div>
                                                                        <div class="text-slate-500"
                                                                            x-text="formatMoney(ad.amount || 0)"></div>
                                                                    </div>
                                                                    <div>
                                                                        <input type="checkbox" :value="ad.date"
                                                                            x-model="items[{{ $index }}].selectedAddons"
                                                                            class="h-4 w-4">
                                                                    </div>
                                                                </li>
                                                            </template>
                                                        </ul>
                                                    </div>

                                                    <div class="mt-4 flex justify-end">
                                                        <button type="button"
                                                            @click="applySelectedAddons({{ $index }})"
                                                            class="px-3 py-2 rounded bg-slate-100 text-sm">Done</button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </template>

                                    <template x-if="(items[{{ $index }}].addonTotal || 0) <= 0">
                                        <span>-</span>
                                    </template>
                                </td> --}}

                                {{-- Gross
                                <td class="px-4 py-3 text-right text-sm text-slate-800">
                                    <span x-text="formatMoney(items[{{ $index }}].gross)"></span>
                                </td> -- }}

                                {{-- Cash (weekly only, editable, max weekly_amount) --}}
                                <td class="px-4 py-3 text-right align-top">
                                    <!-- Cash -->
                                    <input type="number" min="0" step="0.01"
                                        x-model.number="items[{{ $index }}].cash"
                                        @input="recalcRow({{ $index }})"
                                        class="w-24 text-right border rounded px-2 py-1">

                                    {{-- <div class="mt-2 text-xs text-slate-600">
                                        Total cash:
                                        <strong class="ml-1" x-text="formatMoney(items[{{ $index }}].Cash || 0)">
                                        </strong>
                                    </div> --}}
                                </td>


                                {{-- Bank (gross - weeklyCash - addonCash) --}}
                                <td class="px-4 py-3 text-right align-top">
                                    <!-- Bank -->
                                    <input type="number" min="0" step="0.01"
                                        x-model.number="items[{{ $index }}].bank"
                                        @input="updateFromBank({{ $index }})"
                                        class="w-24 text-right border rounded px-2 py-1">
                                </td>


                                {{-- Hidden inputs for submit --}}
                                <td class="hidden">
                                    <input type="hidden" name="items[{{ $index }}][employee_id]"
                                        value="{{ $emp->id }}">
                                    <input type="hidden" name="items[{{ $index }}][type]"
                                        value="{{ $row['type'] }}">

                                    <input type="hidden" name="items[{{ $index }}][weekly_amount]"
                                        x-model="items[{{ $index }}].weekly_amount">

                                    <input type="hidden" name="items[{{ $index }}][gross]"
                                        x-model="items[{{ $index }}].gross">

                                    <input type="hidden" name="items[{{ $index }}][cash]"
                                        x-model="items[{{ $index }}].cash">

                                    <input type="hidden" name="items[{{ $index }}][bank]"
                                        x-model="items[{{ $index }}].bank">

                                    <input type="hidden" name="items[{{ $index }}][addons]"
                                        :value="JSON.stringify(items[{{ $index }}].addons || [])">

                                    <input type="hidden" name="items[{{ $index }}][addons_selected_dates]"
                                        :value="JSON.stringify(items[{{ $index }}].selectedAddons || [])">

                                    <input type="hidden" name="items[{{ $index }}][total_days]"
                                        value="{{ $row['total_days'] }}">
                                    <input type="hidden" name="items[{{ $index }}][present_days]"
                                        value="{{ $row['present_days'] }}">
                                    <input type="hidden" name="items[{{ $index }}][total_hours]"
                                        value="{{ $row['total_hours'] }}">
                                    <input type="hidden" name="items[{{ $index }}][overtime]"
                                        value="{{ $row['type'] === 'daily_rate' ? $row['overtime_amount'] ?? 0 : $row['overtime_hours'] ?? 0 }}">
                                </td>

                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="px-4 py-6 text-center text-sm text-slate-500">
                                    No attendance found for this week. Please fill attendance first.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>

                    @if ($rows->count())
                        <tfoot class="bg-slate-50 text-sm">
                            <tr>
                                <td colspan="4" class="px-4 py-3 text-right font-semibold text-slate-700">
                                    Totals
                                </td>
                                <td class="px-4 py-3 text-right font-semibold text-slate-800">
                                    <span x-text="formatMoney(totals.weeklyAmount)"></span>
                                </td>
                                <td class="px-4 py-3 text-right font-semibold text-slate-800">
                                    <span x-text="formatMoney(totals.cash)"></span>
                                </td>
                                <td class="px-4 py-3 text-right font-semibold text-slate-800">
                                    <span x-text="formatMoney(totals.bank)"></span>
                                </td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>

            @if ($rows->count())
                {{-- Summary table you asked (weekly cash + addon cash + total cash + bank) --}}
                <div class="p-4 border-t border-slate-100 bg-white">
                    <div class="text-sm font-semibold text-slate-800 mb-2">Cash and Bank Summary</div>

                    <table class="text-sm w-full">
                        <tbody class="divide-y">
                            <tr>
                                <td class="py-2 text-slate-600 flex items-center relative group">
                                    <span>Total Gross</span>
                                    <svg xmlns="http://www.w3.org/2000/svg"
                                        class="h-4 w-4 ml-2 text-slate-500 cursor-pointer" viewBox="0 0 24 24"
                                        fill="currentColor" aria-hidden="true">
                                        <path fill-rule="evenodd"
                                            d="M12 22C6.48 22 2 17.52 2 12S6.48 2 12 2s10 4.48 10 10-4.48 10-10 10zm0-18C7.03 4 4 7.03 4 12s3.03 8 8 8 8-3.03 8-8-3.03-8-8-8zm-1 13h2v-2h-2v2zm0-4h2V7h-2v6z" />
                                    </svg>
                                    <div
                                        class="absolute left-0 mt-2 bg-white text-xs text-slate-500 p-2 rounded shadow-lg opacity-0 group-hover:opacity-100 transition-opacity duration-300 pointer-events-none">
                                        The total gross amount before any deductions or additions.
                                    </div>
                                </td>
                                <td class="py-2 text-right font-semibold" x-text="formatMoney(totals.gross)"></td>
                            </tr>
                            <tr>
                                <td class="py-2 text-slate-600 flex items-center relative group">
                                    <span>Weekly Cash</span>
                                    <svg xmlns="http://www.w3.org/2000/svg"
                                        class="h-4 w-4 ml-2 text-slate-500 cursor-pointer" viewBox="0 0 24 24"
                                        fill="currentColor" aria-hidden="true">
                                        <path fill-rule="evenodd"
                                            d="M12 22C6.48 22 2 17.52 2 12S6.48 2 12 2s10 4.48 10 10-4.48 10-10 10zm0-18C7.03 4 4 7.03 4 12s3.03 8 8 8 8-3.03 8-8-3.03-8-8-8zm-1 13h2v-2h-2v2zm0-4h2V7h-2v6z" />
                                    </svg>
                                    <div
                                        class="absolute left-0 mt-2 bg-white text-xs text-slate-500 p-2 rounded shadow-lg opacity-0 group-hover:opacity-100 transition-opacity duration-300 pointer-events-none">
                                        Total of cash given to the employee in the current week.
                                    </div>
                                </td>
                                <td class="py-2 text-right font-semibold" x-text="formatMoney(totals.weeklyCash)"></td>
                            </tr>
                            <tr>
                                <td class="py-2 text-slate-600 flex items-center relative group">
                                    <span>Addon Cash</span>
                                    <svg xmlns="http://www.w3.org/2000/svg"
                                        class="h-4 w-4 ml-2 text-slate-500 cursor-pointer" viewBox="0 0 24 24"
                                        fill="currentColor" aria-hidden="true">
                                        <path fill-rule="evenodd"
                                            d="M12 22C6.48 22 2 17.52 2 12S6.48 2 12 2s10 4.48 10 10-4.48 10-10 10zm0-18C7.03 4 4 7.03 4 12s3.03 8 8 8 8-3.03 8-8-3.03-8-8-8zm-1 13h2v-2h-2v2zm0-4h2V7h-2v6z" />
                                    </svg>
                                    <div
                                        class="absolute left-0 mt-2 bg-white text-xs text-slate-500 p-2 rounded shadow-lg opacity-0 group-hover:opacity-100 transition-opacity duration-300 pointer-events-none">
                                        Cash given to the employee for overtime or additional work beyond regular hours.
                                    </div>
                                </td>
                                <td class="py-2 text-right font-semibold" x-text="formatMoney(totals.addonCash)"></td>
                            </tr>
                            <tr>
                                <td class="py-2 text-slate-600 flex items-center relative group">
                                    <span>Total Cash</span>
                                    <svg xmlns="http://www.w3.org/2000/svg"
                                        class="h-4 w-4 ml-2 text-slate-500 cursor-pointer" viewBox="0 0 24 24"
                                        fill="currentColor" aria-hidden="true">
                                        <path fill-rule="evenodd"
                                            d="M12 22C6.48 22 2 17.52 2 12S6.48 2 12 2s10 4.48 10 10-4.48 10-10 10zm0-18C7.03 4 4 7.03 4 12s3.03 8 8 8 8-3.03 8-8-3.03-8-8-8zm-1 13h2v-2h-2v2zm0-4h2V7h-2v6z" />
                                    </svg>
                                    <div
                                        class="absolute left-0 mt-2 bg-white text-xs text-slate-500 p-2 rounded shadow-lg opacity-0 group-hover:opacity-100 transition-opacity duration-300 pointer-events-none">
                                        The total amount of cash provided to the employee, including weekly cash and addon
                                        cash.
                                    </div>
                                </td>
                                <td class="py-2 text-right font-semibold" x-text="formatMoney(totals.cash)"></td>
                            </tr>
                            <tr>
                                <td class="py-2 text-slate-600 flex items-center relative group">
                                    <span>Total Bank</span>
                                    <svg xmlns="http://www.w3.org/2000/svg"
                                        class="h-4 w-4 ml-2 text-slate-500 cursor-pointer" viewBox="0 0 24 24"
                                        fill="currentColor" aria-hidden="true">
                                        <path fill-rule="evenodd"
                                            d="M12 22C6.48 22 2 17.52 2 12S6.48 2 12 2s10 4.48 10 10-4.48 10-10 10zm0-18C7.03 4 4 7.03 4 12s3.03 8 8 8 8-3.03 8-8-3.03-8-8-8zm-1 13h2v-2h-2v2zm0-4h2V7h-2v6z" />
                                    </svg>

                                    <div
                                        class="absolute left-0 mt-2 bg-white text-xs text-slate-500 p-2 rounded shadow-lg opacity-0 group-hover:opacity-100 transition-opacity duration-300 pointer-events-none">
                                        The amount remaining after deducting the total cash from the gross total.
                                    </div>
                                </td>
                                <td class="py-2 text-right font-semibold" x-text="formatMoney(totals.bank)"></td>
                            </tr>
                        </tbody>
                    </table>




                </div>

                <div class="px-4 py-3 border-t border-slate-100 flex justify-end">
                    <button type="submit"
                        class="px-4 py-2 rounded-lg bg-slate-900 text-white text-sm font-medium hover:bg-slate-800">
                        Save weekly payroll
                    </button>
                </div>
            @endif
        </form>
    </div>

    <script>
        window.payrollServerRows = @json($rows);
    </script>

    <script>
        function payrollPage() {
            return {
                items: [],
                totals: {
                    weeklyAmount: 0,
                    cash: 0,
                    bank: 0,
                },

                year: {{ $year }},
                month: {{ $month }},

                /*
                 * Count payroll weeks:
                 * ISO weeks whose MONDAY is inside the given month
                 */
                getPayrollWeeksInMonth(year, month) {
                    const weeks = new Set();

                    let d = new Date(year, month - 1, 1);

                    // move to first Monday
                    while (d.getDay() !== 1) {
                        d.setDate(d.getDate() + 1);
                    }

                    while (d.getMonth() === month - 1) {
                        const iso = new Date(d);
                        iso.setDate(iso.getDate() + 4 - (iso.getDay() || 7));
                        const yearStart = new Date(iso.getFullYear(), 0, 1);
                        const weekNo = Math.ceil((((iso - yearStart) / 86400000) + 1) / 7);

                        weeks.add(weekNo);
                        d.setDate(d.getDate() + 7);
                    }

                    return weeks.size || 1;
                },

                init(serverRows) {
                    if (!Array.isArray(serverRows)) {
                        console.error('Payroll init failed: serverRows is not an array', serverRows);
                        return;
                    }

                    const weeksInMonth = this.getPayrollWeeksInMonth(this.year, this.month);

                    this.items = serverRows.map(row => {
                        const weeklyAmount = Number(row.weekly_amount || 0);
                        const savedCash = Number(row.cash_amount || 0);
                        const savedBank = Number(row.bank_amount || 0);

                        // Payroll already saved if either value exists
                        const isSaved = savedCash > 0;

                        let cashAmount;
                        let bankAmount;

                        if (isSaved) {
                            cashAmount = savedCash;
                            bankAmount = savedBank;
                        } else {
                            console.log(savedBank);

                            bankAmount = weeksInMonth > 0 ?
                                savedBank / weeksInMonth :
                                savedBank;

                            cashAmount = weeklyAmount - bankAmount;
                        }

                        // Normalize values
                        const cash = Math.round(Math.max(0, cashAmount) * 100) / 100;
                        const bank = Math.round(Math.max(0, bankAmount) * 100) / 100;

                        return {
                            weekly_amount: weeklyAmount,
                            cash: cash,
                            bank: bank,
                            employee_id: row.employee?.id ?? null,
                            type: row.type ?? null,
                            gross_amount: Number(row.gross_amount || 0),
                            overtime_amount: Number(row.overtime_amount || 0),
                            addons: row.addons || [],
                        };
                    });

                    this.recalculateTotals();
                },


                recalcRow(index) {
                    const it = this.items[index];
                    const weekly = Number(it.weekly_amount || 0);

                    let cash = Number(it.cash || 0);
                    if (!isFinite(cash) || cash < 0) cash = 0;
                    if (cash > weekly) cash = weekly;

                    cash = Math.round(cash * 100) / 100;

                    it.cash = cash;
                    it.bank = Math.round((weekly - cash) * 100) / 100;

                    this.recalculateTotals();
                },

                updateFromBank(index) {
                    const it = this.items[index];
                    const weekly = Number(it.weekly_amount || 0);

                    let bank = Number(it.bank || 0);
                    if (!isFinite(bank) || bank < 0) bank = 0;
                    if (bank > weekly) bank = weekly;

                    bank = Math.round(bank * 100) / 100;

                    it.bank = bank;
                    it.cash = Math.round((weekly - bank) * 100) / 100;

                    this.recalculateTotals();
                },

                recalculateTotals() {
                    let weekly = 0,
                        cash = 0,
                        bank = 0;

                    for (const it of this.items) {
                        weekly += Number(it.weekly_amount || 0);
                        cash += Number(it.cash || 0);
                        bank += Number(it.bank || 0);
                    }

                    this.totals.weeklyAmount = weekly;
                    this.totals.cash = cash;
                    this.totals.bank = bank;
                },

                formatMoney(v) {
                    return Number(v || 0).toFixed(2);
                },

                saveRow(index) {
                    const it = this.items[index];
                    console.log("Saving row", it);

                    fetch("{{ route('payroll.saveWeek') }}", {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        },
                        body: JSON.stringify({
                            year: this.year,
                            week: {{ $week }},
                            items: [{
                                employee_id: it.employee_id,
                                type: it.type,
                                weekly_amount: it.weekly_amount,
                                cash: it.cash,
                                bank: it.bank,
                            }]
                        }),
                    });
                }
            };
        }
    </script>
@endsection
