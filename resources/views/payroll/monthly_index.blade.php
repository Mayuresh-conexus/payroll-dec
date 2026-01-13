@extends('layouts.app')

@section('title', 'Monthly payroll')
@section('page_title', 'Monthly payroll')

@section('content')
    <div x-data="monthlyNotes()" x-init="init({{ json_encode($rows->map(function ($r) {return $r['note'] ?? '';})->values()) }})" class="space-y-6">

        <form method="get" action="{{ route('payroll.monthly.index') }}"
            class="bg-white rounded-xl shadow-sm border border-slate-200 p-4 flex items-center gap-4 text-sm">
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Month</label>
                <input type="month" name="month" value="{{ $month }}"
                    class="rounded-lg border-slate-200 text-sm focus:ring-slate-500 focus:border-slate-500">
                <button type="submit"
                    class="px-4 py-2 ml-2 rounded-lg bg-slate-900 text-white text-sm font-medium">Load</button>
            </div>

            <div class="ml-auto flex items-center">

                @php
                    $weekSet = [];

                    foreach ($rows as $row) {
                        preg_match_all('/\d+/', $row['weeks_display'] ?? '', $m);
                        foreach ($m[0] as $w) {
                            $weekSet[(int) $w] = true;
                        }
                    }

                    $weeks = array_keys($weekSet);
                    sort($weeks);

                    $uniqueWeeksDisplay = collect($weeks)->map(fn($w) => 'wk' . $w)->implode(' ');
                @endphp


                <div class="flex flex-wrap gap-2 items-center">
                    @foreach ($weeks as $w)
                        <span class="px-3 py-1 font-medium rounded-full text-xs bg-slate-900 text-white">
                            Week{{ $w }}
                        </span>
                    @endforeach
                </div>

                <a href="{{ route('payroll.exportMonthXlsx', ['month' => $month]) }}"
                    class="ml-2 px-4 py-2 rounded-lg border border-slate-300 text-sm font-medium text-slate-700 hover:bg-slate-50">Export
                    XLSX</a>
            </div>
        </form>

        <form action="{{ route('payroll.saveMonth') }}" method="post"
            class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
            @csrf
            <input type="hidden" name="month" value="{{ $month }}">

            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-slate-500 uppercase text-xs font-semibold">
                        <tr>
                            <th class="px-4 py-3 text-left">Code</th>
                            <th class="px-4 py-3 text-left">Name</th>
                            <th class="px-4 py-3 text-center">Type</th>
                            <th class="px-4 py-3 text-right">Weekly</th>
                            <th class="px-4 py-3 text-right">Addons</th>
                            <th class="px-4 py-3 text-right">Gross</th>
                            <th class="px-4 py-3 text-right">Cash</th>
                            <th class="px-4 py-3 text-right">Bank</th>
                            <th class="px-4 py-3 text-left">Transfer ID</th>
                            <th class="px-4 py-3 text-left">Note</th>
                            <th class="px-4 py-3 text-left">Transfer Date</th>
                            <th class="px-4 py-3 text-left">Status</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-slate-100">
                        @php
                            $monthRun = \App\Models\PayrollRun::where('period_type', 'monthly')
                                ->where('month', $month)
                                ->first();
                            $itemsByEmployee = collect();
                            if ($monthRun && $monthRun->items) {
                                $itemsByEmployee = $monthRun->items->keyBy('employee_id');
                            }
                        @endphp

                        @forelse($rows as $index => $row)
                            @php
                                $emp = $row['employee'];
                                $pay = $itemsByEmployee[$emp->id] ?? null;
                                $weeklyAmount = $pay->weekly_amount ?? ($pay->gross_amount ?? null);
                                $addons = $pay->addons ?? null;
                                if (is_string($addons)) {
                                    $addons = json_decode($addons, true);
                                }
                                $addonsTotal = 0;
                                if (is_array($addons)) {
                                    foreach ($addons as $ad) {
                                        $addonsTotal += (float) ($ad['amount'] ?? 0);
                                    }
                                }
                            @endphp
                            <tr class="hover:bg-slate-50/80">
                                <td class="px-4 py-3 font-mono text-xs text-slate-600">{{ $emp->employee_code }}</td>
                                <td class="px-4 py-3 text-sm font-medium text-slate-800">{{ $emp->name }}</td>
                                <td class="px-4 py-3 text-center text-xs">
                                    @if ($row['type'] === 'daily_rate')
                                        <span
                                            class="inline-flex px-2 py-1 rounded-full bg-emerald-50 text-emerald-700">Daily</span>
                                    @else
                                        <span
                                            class="inline-flex px-2 py-1 rounded-full bg-blue-50 text-blue-700">Hourly</span>
                                    @endif
                                </td>

                                <td class="px-4 py-3 text-right text-sm text-slate-800">
                                    {{ $weeklyAmount !== null ? number_format($weeklyAmount, 2) : '-' }}</td>
                                <td class="px-4 py-3 text-right text-sm">
                                    @if ($addonsTotal > 0)
                                        <details class="text-sm">
                                            <summary class="cursor-pointer">{{ number_format($addonsTotal, 2) }} ▾
                                            </summary>
                                            <div class="mt-2 text-xs text-slate-600">
                                                <ul class="list-disc list-inside">
                                                    @foreach ($addons as $ad)
                                                        <li>{{ $ad['date'] ?? 'n/a' }} —
                                                            {{ number_format((float) ($ad['amount'] ?? 0), 2) }}</li>
                                                    @endforeach
                                                </ul>
                                            </div>
                                        </details>
                                    @else
                                        -
                                    @endif
                                </td>

                                <td class="px-4 py-3 text-right text-sm text-slate-800">
                                    {{ number_format($row['gross_amount'], 2) }}</td>
                                <td class="px-4 py-3 text-right text-sm text-slate-800">
                                    {{ number_format($row['cash_amount'] ?? 0, 2) }}</td>
                                <td class="px-4 py-3 text-right text-sm text-slate-800">
                                    {{ number_format($row['bank_amount'], 2) }}</td>



                                <td class="px-4 py-3 text-left">
                                    <input type="text" name="items[{{ $index }}][transfer_id]"
                                        value="{{ $row['transfer_id'] ?? '' }}"
                                        class="rounded-md border border-slate-200 px-2 py-1 text-sm">
                                </td>

                                <td class="px-4 py-3 text-left">
                                    <button type="button" @click="open({{ $index }})"
                                        class="px-3 py-1 rounded-md bg-slate-100 border text-sm">Add Note</button>
                                    <input type="hidden" name="items[{{ $index }}][note]"
                                        x-model="items[{{ $index }}]">
                                </td>

                                <td class="px-4 py-3 text-left">
                                    <input type="date" name="items[{{ $index }}][transfer_date]"
                                        value="{{ $row['transfer_date'] ?? '' }}"
                                        class="rounded-md border border-slate-200 px-2 py-1 text-sm">
                                </td>
                                <td class="px-4 py-3 text-left">
                                    <select name="items[{{ $index }}][transfer_status]"
                                        class="rounded-md border border-slate-200 px-2 py-1 text-sm">
                                        <option value="pending" @selected(($row['transfer_status'] ?? '') === 'pending')>Pending</option>
                                        <option value="completed" @selected(($row['transfer_status'] ?? '') === 'completed')>Completed</option>
                                        <option value="failed" @selected(($row['transfer_status'] ?? '') === 'failed')>Failed</option>
                                    </select>
                                </td>

                                <input type="hidden" name="items[{{ $index }}][employee_id]"
                                    value="{{ $emp->id }}">
                                <input type="hidden" name="items[{{ $index }}][type]" value="{{ $row['type'] }}">
                                <input type="hidden" name="items[{{ $index }}][gross]"
                                    value="{{ $row['gross_amount'] }}">
                                <input type="hidden" name="items[{{ $index }}][bank]"
                                    value="{{ $row['bank_amount'] }}">
                                <input type="hidden" name="items[{{ $index }}][cash]"
                                    value="{{ $row['cash_amount'] ?? 0 }}">
                                <input type="hidden" name="items[{{ $index }}][weekly_amount]"
                                    value="{{ $weeklyAmount ?? '' }}">
                                <input type="hidden" name="items[{{ $index }}][addons]"
                                    value='{{ json_encode($addons ?? []) }}'>
                                <input type="hidden" name="items[{{ $index }}][overtime]"
                                    value="{{ $row['type'] === 'daily_rate' ? $row['overtime_amount'] ?? 0 : $row['overtime_hours'] ?? 0 }}">
                            </tr>
                        @empty
                            <tr>
                                <td colspan="12" class="px-4 py-6 text-center text-sm text-slate-500">No payroll items
                                    available for this month.</td>
                            </tr>
                        @endforelse
                    </tbody>

                    @if ($rows->count())
                        <tfoot class="bg-slate-50 text-sm">
                            <tr>
                                <td colspan="5" class="px-4 py-3 text-right font-semibold text-slate-700">Totals</td>
                                <td class="px-4 py-3 text-right font-semibold text-green-800">
                                    {{ number_format($totals['gross'], 2) }}</td>
                                <td class="px-4 py-3 text-right font-semibold text-green-800">
                                    {{ number_format($totals['cash'], 2) }}</td>
                                <td class="px-4 py-3 text-right font-semibold text-green-800">
                                    {{ number_format($totals['bank'], 2) }}</td>
                                <td colspan="4"></td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>

            @if ($rows->count())
                <div class="px-4 py-3 border-t border-slate-100 flex justify-end">
                    <button type="submit" class="px-4 py-2 rounded-lg bg-slate-900 text-white text-sm font-medium">Save
                        monthly payroll</button>
                </div>
            @endif
        </form>

        <!-- Note modal -->
        <div x-show="show" x-cloak class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-40">
            <div class="bg-white rounded-lg shadow-lg w-1/2 p-4">
                <h3 class="text-lg font-semibold mb-2">Edit Note</h3>
                <textarea x-model="currentNote" class="w-full h-40 border rounded-md p-2" placeholder="Add a note"></textarea>
                <div class="flex justify-end gap-2 mt-3">
                    <button type="button" @click="close()" class="px-4 py-2 rounded bg-gray-100">Cancel</button>
                    <button type="button" @click="save()"
                        class="px-4 py-2 rounded bg-slate-900 text-white">Save</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        function monthlyNotes() {
            return {
                items: [],
                show: false,
                currentIndex: null,
                currentNote: '',
                init(notes) {
                    this.items = Array.isArray(notes) ? notes : [];
                },
                open(i) {
                    this.currentIndex = i;
                    this.currentNote = this.items[i] ?? '';
                    this.show = true;
                },
                close() {
                    this.show = false;
                    this.currentIndex = null;
                },
                save() {
                    if (this.currentIndex !== null) {
                        this.items[this.currentIndex] = this.currentNote;
                    }
                    this.close();
                }
            }
        }
    </script>
@endsection
