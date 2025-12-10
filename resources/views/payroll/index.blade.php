@extends('layouts.app')

@section('title', 'Weekly payroll')
@section('page_title', 'Weekly payroll')

@section('content')
    <div x-data="payrollPage()" x-init="init({{ json_encode($rows) }})" class="space-y-6">

        {{-- Filter bar --}}
        <form method="get" action="{{ route('payroll.index') }}"
            class="bg-white rounded-xl shadow-sm border border-slate-200 p-4 flex flex-wrap items-center gap-4 text-sm">
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Year</label>
                <select name="year"
                    class="rounded-lg border-slate-200 text-sm focus:ring-slate-500 focus:border-slate-500">
                    @for ($y = now()->year - 1; $y <= now()->year + 1; $y++)
                        <option value="{{ $y }}" @selected($y == $year)>{{ $y }}</option>
                    @endfor
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Week</label>
                <select name="week"
                    class="rounded-lg border-slate-200 text-sm focus:ring-slate-500 focus:border-slate-500">
                    @for ($w = 1; $w <= 52; $w++)
                        <option value="{{ $w }}" @selected($w == $week)>Week {{ $w }}</option>
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
                            <th class="px-4 py-3 text-right">Gross salary</th>
                            <th class="px-4 py-3 text-right">Cash</th>
                            <th class="px-4 py-3 text-right">Bank</th>
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
                                <td class="px-4 py-3 text-center text-xs text-slate-600">
                                    @if ($row['type'] === 'daily_rate')
                                        {{ $row['present_days'] }}/{{ $row['total_days'] }} days
                                    @else
                                        {{ $row['total_hours'] }} hrs
                                        @if ($row['overtime_hours'])
                                            + {{ $row['overtime_hours'] }} OT
                                        @endif
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right text-sm text-slate-800">
                                    {{ number_format($row['gross_amount'], 2) }}
                                </td>

                                {{-- Cash input --}}
                                <td class="px-4 py-3 text-right">
                                    <input type="number" step="0.01" min="0"
                                        x-model.number="items[{{ $index }}].cash"
                                        @input="updateBank({{ $index }})"
                                        class="w-24 text-right rounded-md border border-slate-200 px-2 py-1.5 text-sm focus:ring-slate-500 focus:border-slate-500"
                                        placeholder="0.00">
                                </td>

                                {{-- Bank calculated --}}
                                <td class="px-4 py-3 text-right text-sm text-slate-800">
                                    <span x-text="formatMoney(items[{{ $index }}].bank)"></span>
                                </td>

                                {{-- hidden inputs for submit --}}
                                <input type="hidden" name="items[{{ $index }}][employee_id]"
                                    value="{{ $emp->id }}">
                                <input type="hidden" name="items[{{ $index }}][type]" value="{{ $row['type'] }}">
                                <input type="hidden" name="items[{{ $index }}][total_days]"
                                    value="{{ $row['total_days'] }}">
                                <input type="hidden" name="items[{{ $index }}][present_days]"
                                    value="{{ $row['present_days'] }}">
                                <input type="hidden" name="items[{{ $index }}][total_hours]"
                                    value="{{ $row['total_hours'] }}">
                                <input type="hidden" name="items[{{ $index }}][overtime]"
                                    value="{{ $row['overtime_hours'] }}">
                                <input type="hidden" name="items[{{ $index }}][gross]"
                                    :value="items[{{ $index }}].gross">
                                <input type="hidden" name="items[{{ $index }}][cash]"
                                    :value="items[{{ $index }}].cash">
                                <input type="hidden" name="items[{{ $index }}][bank]"
                                    :value="items[{{ $index }}].bank">
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-6 text-center text-sm text-slate-500">
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
                                    <span x-text="formatMoney(totalGross)"></span>
                                </td>
                                <td class="px-4 py-3 text-right font-semibold text-slate-800">
                                    <span x-text="formatMoney(totalCash)"></span>
                                </td>
                                <td class="px-4 py-3 text-right font-semibold text-slate-800">
                                    <span x-text="formatMoney(totalBank)"></span>
                                </td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>

            @if ($rows->count())
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
        function payrollPage() {
            return {
                items: [],
                totalGross: 0,
                totalCash: 0,
                totalBank: 0,

                init(serverRows) {
                    this.items = serverRows.map(row => ({
                        gross: parseFloat(row.gross_amount ?? 0),
                        cash: parseFloat(row.cash_amount ?? 0),
                        bank: parseFloat(row.bank_amount ?? row.gross_amount ?? 0),
                    }));
                    this.recalculateTotals();
                },

                updateBank(index) {
                    const item = this.items[index];
                    if (item.cash < 0) item.cash = 0;
                    if (item.cash > item.gross) item.cash = item.gross;
                    item.bank = item.gross - item.cash;
                    this.recalculateTotals();
                },

                recalculateTotals() {
                    this.totalGross = this.items.reduce((sum, i) => sum + i.gross, 0);
                    this.totalCash = this.items.reduce((sum, i) => sum + i.cash, 0);
                    this.totalBank = this.items.reduce((sum, i) => sum + i.bank, 0);
                },

                formatMoney(v) {
                    return v.toFixed(2);
                }
            };
        }
    </script>
@endsection
