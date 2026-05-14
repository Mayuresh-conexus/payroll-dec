{{-- payroll/partials/_table.blade.php — Payroll data table: attendance, weekly, cash, bank --}}
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
                    <th class="px-4 py-3 text-right">Weekly Cash</th>
                    <th class="px-4 py-3 text-right">Weekly Bank</th>
                    <th class="px-4 py-3 text-center">Payslip</th>
                </tr>
            </thead>

            <tbody class="divide-y divide-slate-100">
                @forelse($rows as $index => $row)
                    @php $emp = $row['employee']; @endphp
                    <tr class="hover:bg-slate-50/80">
                        <td class="px-4 py-3 font-mono text-xs text-slate-600">{{ $emp->employee_code }}</td>
                        <td class="px-4 py-3 text-sm font-medium text-slate-800">{{ $emp->name }}</td>
                        <td class="px-4 py-3 text-center text-xs">
                            @if ($row['type'] === 'daily_rate')
                                <span class="inline-flex px-2 py-1 rounded-full bg-emerald-50 text-emerald-700">Daily</span>
                            @else
                                <span class="inline-flex px-2 py-1 rounded-full bg-brand-50 text-brand-700">Hourly</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-center text-xs text-slate-600">
                            @if ($row['type'] === 'daily_rate')
                                @if (!empty($row['sun_present']))
                                    {{ $row['present_days'] - 1 }}/{{ $row['total_days'] }} days
                                    + <span class="inline-flex items-center ml-1 px-2 py-0.5 rounded-full bg-orange-50 text-orange-700 text-xs font-semibold">Sun</span>
                                @else
                                    {{ $row['present_days'] }}/{{ $row['total_days'] }} days
                                @endif
                                @if (!empty($row['overtime_amount']))
                                    + <span class="inline-flex items-center ml-2 px-2 py-0.5 rounded-full bg-purple-50 text-purple-700 text-xs font-semibold">
                                        {{ number_format($row['overtime_amount'], 2) }} OT
                                    </span>
                                @endif
                            @else
                                @if (!empty($row['sun_present']))
                                    {{ $row['total_hours'] - $row['sun_hours'] }} hrs
                                    @if ($row['overtime_hours'])
                                        + <span class="inline-flex items-center ml-1 px-2 py-0.5 rounded-full bg-purple-50 text-purple-700 text-xs font-semibold">{{ $row['overtime_hours'] }} hrs OT</span>
                                    @endif
                                    + <span class="inline-flex items-center ml-1 px-2 py-0.5 rounded-full bg-orange-50 text-orange-700 text-xs font-semibold">{{ min(6, $row['sun_hours'] ?? 0) }} hrs Sun</span>
                                @else
                                    {{ $row['total_hours'] }} hrs
                                    @if ($row['overtime_hours'])
                                        + {{ $row['overtime_hours'] }} OT
                                    @endif
                                @endif
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right text-sm text-slate-800">
                            <span x-text="formatMoney(items[{{ $index }}].weekly_amount)"></span>
                        </td>
                        <td class="px-4 py-3 text-right align-top">
                            <input type="number" min="0" step="0.01"
                                x-model.number="items[{{ $index }}].cash"
                                @input="recalcRow({{ $index }})"
                                class="w-24 text-right border border-slate-200 rounded-lg px-3 py-1.5 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500/30 outline-none transition-all shadow-sm">
                        </td>
                        <td class="px-4 py-3 text-right align-top">
                            <input type="number" min="0" step="0.01"
                                x-model.number="items[{{ $index }}].bank"
                                @input="updateFromBank({{ $index }})"
                                class="w-24 text-right border border-slate-200 rounded-lg px-3 py-1.5 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500/30 outline-none transition-all shadow-sm">
                        </td>

                        {{-- Payslip PDF download --}}
                        <td class="px-4 py-3 text-center align-top">
                            <a href="{{ route('payroll.payslip', ['year' => $year, 'week' => $week, 'employee' => $emp->id]) }}"
                                target="_blank"
                                title="Download payslip for {{ $emp->name }}"
                                class="inline-flex items-center justify-center w-7 h-7 rounded-lg border border-slate-200 text-slate-400 hover:text-slate-700 hover:border-slate-400 hover:bg-slate-50 transition">
                                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0-3-3m3 3 3-3M3 17v3a1 1 0 0 0 1 1h16a1 1 0 0 0 1-1v-3" />
                                </svg>
                            </a>
                        </td>
                        {{-- Hidden inputs for submit --}}
                        <td class="hidden">
                            <input type="hidden" name="items[{{ $index }}][employee_id]" value="{{ $emp->id }}">
                            <input type="hidden" name="items[{{ $index }}][type]"          value="{{ $row['type'] }}">
                            <input type="hidden" name="items[{{ $index }}][weekly_amount]" x-model="items[{{ $index }}].weekly_amount">
                            <input type="hidden" name="items[{{ $index }}][gross]"         x-model="items[{{ $index }}].gross">
                            <input type="hidden" name="items[{{ $index }}][cash]"          x-model="items[{{ $index }}].cash">
                            <input type="hidden" name="items[{{ $index }}][bank]"          x-model="items[{{ $index }}].bank">
                            <input type="hidden" name="items[{{ $index }}][addons]"        :value="JSON.stringify(items[{{ $index }}].addons || [])">
                            <input type="hidden" name="items[{{ $index }}][addons_selected_dates]" :value="JSON.stringify(items[{{ $index }}].selectedAddons || [])">
                            <input type="hidden" name="items[{{ $index }}][total_days]"    value="{{ $row['total_days'] }}">
                            <input type="hidden" name="items[{{ $index }}][present_days]"  value="{{ $row['present_days'] }}">
                            <input type="hidden" name="items[{{ $index }}][total_hours]"   value="{{ $row['total_hours'] }}">
                            <input type="hidden" name="items[{{ $index }}][overtime]"      value="{{ $row['type'] === 'daily_rate' ? $row['overtime_amount'] ?? 0 : $row['overtime_hours'] ?? 0 }}">
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
                        <td colspan="4" class="px-4 py-3 text-right font-semibold text-slate-700">Totals</td>
                        <td class="px-4 py-3 text-right font-semibold text-slate-800"><span x-text="formatMoney(totals.weeklyAmount)"></span></td>
                        <td class="px-4 py-3 text-right font-semibold text-slate-800"><span x-text="formatMoney(totals.cash)"></span></td>
                        <td class="px-4 py-3 text-right font-semibold text-slate-800"><span x-text="formatMoney(totals.bank)"></span></td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>

    @if ($rows->count())
        @include('payroll.partials._cash_summary')

        <div class="px-4 py-3 border-t border-slate-100 flex justify-end">
            <button type="submit"
                class="px-4 py-2 rounded-lg bg-slate-900 text-white text-sm font-medium hover:bg-slate-800">
                Save weekly payroll
            </button>
        </div>
    @endif
</form>
