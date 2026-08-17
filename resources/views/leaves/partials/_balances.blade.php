{{-- leaves/partials/_balances.blade.php — entitlement, taken and remaining per employee --}}
<div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
    <div class="px-4 py-3 border-b border-slate-100 flex items-center justify-between">
        <div>
            <h2 class="text-sm font-semibold text-slate-800">Balances</h2>
            <p class="mt-0.5 text-xs text-slate-500">
                Each leave year runs from the employee's joining anniversary.
            </p>
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-slate-500 uppercase text-xs font-semibold">
                <tr>
                    <th class="px-4 py-3 text-left">Employee</th>
                    <th class="px-4 py-3 text-left">Type</th>
                    <th class="px-4 py-3 text-left">Leave year</th>
                    <th class="px-4 py-3 text-right">Entitlement</th>
                    <th class="px-4 py-3 text-right">Taken</th>
                    <th class="px-4 py-3 text-right">Remaining</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($employees as $employee)
                    @php
                        $balance = $balances[$employee->id];
                        $isHourly = $employee->type === 'hourly';
                        $unit = $balance['unit'] === 'hours' ? 'hrs' : 'days';
                        $short = $balance['remaining'] < 0;
                    @endphp
                    <tr class="hover:bg-slate-50/80">
                        <td class="px-4 py-3">
                            <div class="font-medium text-slate-800">{{ $employee->name }}</div>
                            <div class="font-mono text-[11px] text-slate-400">{{ $employee->employee_code }}</div>
                        </td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-slate-100 text-[11px] text-slate-600">
                                {{ $isHourly ? 'Hourly' : 'Daily' }}
                            </span>
                            @unless ($isHourly)
                                <span class="ml-1 text-[11px] text-slate-400"
                                      title="Entitlement is four weeks of this employee's working week.">
                                    {{ $employee->workingDaysPerWeek() }} d/wk
                                </span>
                                @unless ($employee->weekly_active_days)
                                    <span class="ml-1 inline-flex px-1.5 py-0.5 rounded bg-amber-50 text-amber-700 text-[10px] font-semibold"
                                          title="No working days per week set on this employee, so {{ \App\Models\Employee::DEFAULT_WORKING_DAYS_PER_WEEK }} is assumed. Set it on their profile to fix the entitlement.">
                                        assumed
                                    </span>
                                @endunless
                            @endunless
                        </td>
                        <td class="px-4 py-3 text-xs text-slate-500">
                            {{ $balance['year_start']->format('d M Y') }} → {{ $balance['year_end']->format('d M Y') }}
                            @unless ($employee->joining_date)
                                <span class="ml-1 inline-flex px-1.5 py-0.5 rounded bg-amber-50 text-amber-700 text-[10px] font-semibold"
                                      title="No joining date on file, so the calendar year is used instead of the anniversary.">
                                    calendar year
                                </span>
                            @endunless
                        </td>
                        <td class="px-4 py-3 text-right font-mono text-slate-700">
                            {{ (float) $balance['entitlement'] }} <span class="text-slate-400 text-xs">{{ $unit }}</span>
                            @if ($isHourly)
                                <div class="text-[10px] text-slate-400 font-sans">
                                    {{ \App\Services\LeaveService::HOURLY_ACCRUAL_PERCENT }}% of hours clocked
                                </div>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right font-mono text-slate-700">
                            {{ (float) $balance['taken'] }} <span class="text-slate-400 text-xs">{{ $unit }}</span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <span class="font-mono font-semibold {{ $short ? 'text-rose-600' : 'text-emerald-700' }}"
                                  @if ($short) title="More leave has been taken than earned so far." @endif>
                                {{ (float) $balance['remaining'] }}
                            </span>
                            <span class="text-slate-400 text-xs">{{ $unit }}</span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-6 text-center text-sm text-slate-500">
                            No active employees.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
