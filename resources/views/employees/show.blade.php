@extends('layouts.app')

@section('title', $employee->name . ' — Profile')
@section('page_title', 'Employee Profile')
@section('page_header', $employee->name)
@section('page_subtitle', $employee->employee_code . ($employee->department ? ' · ' . $employee->department : ''))
@section('page_action')
    <a href="{{ route('employees.index') }}"
        class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg border border-slate-200 text-sm text-slate-700 hover:bg-slate-50 transition">
        ← Back to Employees
    </a>
@endsection

@section('content')
<div class="space-y-6">

    {{-- ── Header info card ──────────────────────────────────────────────── --}}
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5 flex flex-wrap items-start gap-6">

        {{-- Avatar --}}
        <div class="h-14 w-14 rounded-full bg-slate-800 text-white flex items-center justify-center text-xl font-bold shrink-0">
            {{ strtoupper(mb_substr($employee->name, 0, 1)) }}
        </div>

        {{-- Core details --}}
        <div class="flex-1 min-w-0 grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-x-6 gap-y-3">
            <div>
                <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">Type</p>
                @if ($employee->type === 'daily_rate')
                    <span class="inline-flex mt-1 px-2 py-0.5 rounded-full text-xs bg-violet-50 text-violet-700">Daily Rate</span>
                @else
                    <span class="inline-flex mt-1 px-2 py-0.5 rounded-full text-xs bg-brand-50 text-brand-700">Hourly</span>
                @endif
            </div>
            <div>
                <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">Status</p>
                @if ($employee->is_active)
                    <span class="inline-flex mt-1 px-2 py-0.5 rounded-full text-xs bg-emerald-50 text-emerald-700">Active</span>
                @else
                    <span class="inline-flex mt-1 px-2 py-0.5 rounded-full text-xs bg-rose-50 text-rose-700">Inactive</span>
                @endif
            </div>
            <div>
                <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">Joining Date</p>
                <p class="text-sm font-medium text-slate-800 mt-1">
                    {{ $employee->joining_date ? $employee->joining_date->format('d M Y') : '—' }}
                </p>
            </div>
            <div>
                <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">Weekly Days</p>
                <p class="text-sm font-medium text-slate-800 mt-1">{{ $employee->weekly_active_days ?? '—' }}</p>
            </div>
            <div>
                <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">Bank Transfer Fix</p>
                <p class="text-sm font-medium text-slate-800 mt-1">
                    {{ $employee->bank_transfer_fix_amount ? '€' . number_format($employee->bank_transfer_fix_amount, 2) : '—' }}
                </p>
            </div>
        </div>

        {{-- Edit button (admin only) --}}
        @can('update', $employee)
            <a href="{{ route('employees.index') }}"
                class="shrink-0 inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-slate-200 text-xs text-slate-600 hover:bg-slate-50 transition">
                Edit in list
            </a>
        @endcan
    </div>

    {{-- ── Current Rates + Bank ──────────────────────────────────────────── --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        @if ($employee->type === 'daily_rate')
            @php $rate = $employee->latestRateOf('daily_rate'); @endphp
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4">
                <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-400 mb-1">Current Daily Rate</p>
                <p class="text-2xl font-bold text-slate-800">€{{ number_format($rate, 2) }}</p>
                <p class="text-xs text-slate-400 mt-0.5">per day</p>
            </div>
        @else
            @php
                $hourlyRate  = $employee->latestRateOf('hourly_rate');
                $hoursPerDay = $employee->latestRateOf('hours_per_day');
            @endphp
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4">
                <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-400 mb-1">Current Hourly Rate</p>
                <p class="text-2xl font-bold text-slate-800">€{{ number_format($hourlyRate, 2) }}</p>
                <p class="text-xs text-slate-400 mt-0.5">per hour</p>
            </div>
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4">
                <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-400 mb-1">Hours / Day</p>
                <p class="text-2xl font-bold text-slate-800">{{ number_format($hoursPerDay, 1) }}</p>
                <p class="text-xs text-slate-400 mt-0.5">standard hours</p>
            </div>
        @endif

        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4">
            <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-400 mb-1">Bank Account</p>
            <p class="text-sm font-medium text-slate-800 truncate">{{ $employee->bank_name ?: '—' }}</p>
            <p class="text-xs text-slate-500 font-mono mt-0.5">{{ $employee->bank_account ?: '—' }}</p>
        </div>

        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4">
            <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-400 mb-1">Rate Changes</p>
            <p class="text-2xl font-bold text-slate-800">{{ $rateHistory->count() }}</p>
            <p class="text-xs text-slate-400 mt-0.5">entries in history</p>
        </div>
    </div>

    {{-- ── Rate Change History ───────────────────────────────────────────── --}}
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
            <h2 class="text-sm font-bold text-slate-800">Salary Change History</h2>
            <span class="text-xs text-slate-400">{{ $rateHistory->count() }} {{ Str::plural('entry', $rateHistory->count()) }}</span>
        </div>

        @if ($rateHistory->isEmpty())
            <div class="px-5 py-8 text-center text-sm text-slate-400">No rate history recorded.</div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full text-xs">
                    <thead class="bg-slate-50 text-slate-500 uppercase text-[11px] font-semibold tracking-wide">
                        <tr>
                            <th class="px-5 py-3 text-left">Rate Type</th>
                            <th class="px-5 py-3 text-right">Amount</th>
                            <th class="px-5 py-3 text-left">Effective From</th>
                            <th class="px-5 py-3 text-left">Effective To</th>
                            <th class="px-5 py-3 text-left">Changed By</th>
                            <th class="px-5 py-3 text-left">Recorded On</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($rateHistory as $rate)
                            <tr class="hover:bg-slate-50/60 transition">
                                <td class="px-5 py-3">
                                    @php
                                        $typeLabel = match($rate->rate_type) {
                                            'daily_rate'   => 'Daily Rate',
                                            'hourly_rate'  => 'Hourly Rate',
                                            'hours_per_day'=> 'Hours / Day',
                                            default        => $rate->rate_type,
                                        };
                                        $typeBadge = match($rate->rate_type) {
                                            'daily_rate'    => 'bg-violet-50 text-violet-700',
                                            'hourly_rate'   => 'bg-brand-50 text-brand-700',
                                            'hours_per_day' => 'bg-slate-100 text-slate-600',
                                            default         => 'bg-slate-100 text-slate-600',
                                        };
                                    @endphp
                                    <span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-semibold {{ $typeBadge }}">
                                        {{ $typeLabel }}
                                    </span>
                                </td>
                                <td class="px-5 py-3 text-right font-mono font-semibold text-slate-800">
                                    {{ $rate->rate_type === 'hours_per_day' ? number_format($rate->amount, 1) : '€' . number_format($rate->amount, 2) }}
                                </td>
                                <td class="px-5 py-3 text-slate-600">
                                    {{ $rate->effective_from ? \Carbon\Carbon::parse($rate->effective_from)->format('d M Y') : '—' }}
                                </td>
                                <td class="px-5 py-3 text-slate-500">
                                    {{ $rate->effective_to ? \Carbon\Carbon::parse($rate->effective_to)->format('d M Y') : 'Current' }}
                                </td>
                                <td class="px-5 py-3 text-slate-600">
                                    {{ $creators->get($rate->created_by, '—') }}
                                </td>
                                <td class="px-5 py-3 text-slate-400">
                                    {{ $rate->created_at ? $rate->created_at->format('d M Y, H:i') : '—' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- ── Last 8 weeks attendance ───────────────────────────────────────── --}}
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100">
            <h2 class="text-sm font-bold text-slate-800">Attendance — Last 8 Weeks</h2>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-xs">
                <thead class="bg-slate-50 text-slate-500 uppercase text-[11px] font-semibold tracking-wide">
                    <tr>
                        <th class="px-5 py-3 text-left">Week</th>
                        @if ($attendanceHistory->first()['type'] === 'daily')
                            <th class="px-5 py-3 text-right">Present Days</th>
                            <th class="px-5 py-3 text-right">Working Days</th>
                            <th class="px-5 py-3 text-right">Overtime (€)</th>
                        @else
                            <th class="px-5 py-3 text-right">Total Hours</th>
                            <th class="px-5 py-3 text-right">OT Hours</th>
                        @endif
                        <th class="px-5 py-3 text-center">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($attendanceHistory as $week)
                        <tr class="hover:bg-slate-50/60 transition {{ !$week['marked'] ? 'opacity-60' : '' }}">
                            <td class="px-5 py-3 font-medium text-slate-700">
                                W{{ $week['week'] }}/{{ $week['year'] }}
                            </td>
                            @if ($week['type'] === 'daily')
                                <td class="px-5 py-3 text-right font-semibold text-slate-800">
                                    {{ $week['marked'] ? $week['present'] : '—' }}
                                </td>
                                <td class="px-5 py-3 text-right text-slate-500">
                                    {{ $week['marked'] ? $week['total'] : '—' }}
                                </td>
                                <td class="px-5 py-3 text-right text-emerald-700">
                                    {{ $week['marked'] && $week['overtime'] > 0 ? '€' . number_format($week['overtime'], 2) : '—' }}
                                </td>
                            @else
                                <td class="px-5 py-3 text-right font-semibold text-slate-800">
                                    {{ $week['marked'] ? number_format($week['total_hours'], 1) : '—' }}
                                </td>
                                <td class="px-5 py-3 text-right text-emerald-700">
                                    {{ $week['marked'] && $week['overtime_hours'] > 0 ? number_format($week['overtime_hours'], 1) : '—' }}
                                </td>
                            @endif
                            <td class="px-5 py-3 text-center">
                                @if (!$week['marked'])
                                    <span class="inline-flex px-2 py-0.5 rounded-full text-[10px] bg-slate-100 text-slate-400">Not marked</span>
                                @elseif ($week['locked'])
                                    <span class="inline-flex px-2 py-0.5 rounded-full text-[10px] bg-slate-100 text-slate-500">Locked</span>
                                @else
                                    <span class="inline-flex px-2 py-0.5 rounded-full text-[10px] bg-emerald-50 text-emerald-700">Open</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- ── Payroll History ───────────────────────────────────────────────── --}}
    @if ($payrollHistory->isNotEmpty())
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100">
            <h2 class="text-sm font-bold text-slate-800">Payroll — Last 6 Runs</h2>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-xs">
                <thead class="bg-slate-50 text-slate-500 uppercase text-[11px] font-semibold tracking-wide">
                    <tr>
                        <th class="px-5 py-3 text-left">Period</th>
                        <th class="px-5 py-3 text-left">Status</th>
                        <th class="px-5 py-3 text-right">Gross</th>
                        <th class="px-5 py-3 text-right">Cash</th>
                        <th class="px-5 py-3 text-right">Bank</th>
                        <th class="px-5 py-3 text-right">Advance</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($payrollHistory as $item)
                        @php $run = $item->payrollRun; @endphp
                        <tr class="hover:bg-slate-50/60 transition">
                            <td class="px-5 py-3 font-medium text-slate-700">
                                @if ($run->period_type === 'weekly')
                                    W{{ $run->week_number }}/{{ $run->year }}
                                @else
                                    {{ $run->month }}
                                @endif
                            </td>
                            <td class="px-5 py-3">
                                @if ($run->status === 'final')
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Final
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-amber-50 text-amber-700">
                                        <span class="w-1.5 h-1.5 rounded-full bg-amber-400"></span> Draft
                                    </span>
                                @endif
                            </td>
                            <td class="px-5 py-3 text-right font-semibold text-slate-800">€{{ number_format($item->gross_amount, 2) }}</td>
                            <td class="px-5 py-3 text-right text-emerald-700">€{{ number_format($item->cash_amount, 2) }}</td>
                            <td class="px-5 py-3 text-right text-brand-700">€{{ number_format($item->bank_amount, 2) }}</td>
                            <td class="px-5 py-3 text-right {{ ($item->advance_given ?? 0) > 0 ? 'text-rose-600 font-semibold' : 'text-slate-400' }}">
                                {{ ($item->advance_given ?? 0) > 0 ? '€' . number_format($item->advance_given, 2) : '—' }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    {{-- ── Audit Trail ───────────────────────────────────────────────────── --}}
    @can('update', $employee)
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
            <h2 class="text-sm font-bold text-slate-800">Audit Trail</h2>
            <span class="text-xs text-slate-400">{{ $auditLogs->count() }} recent changes</span>
        </div>

        @if ($auditLogs->isEmpty())
            <div class="px-5 py-8 text-center text-sm text-slate-400">No audit records found.</div>
        @else
            <div class="divide-y divide-slate-100">
                @foreach ($auditLogs as $log)
                    <div class="px-5 py-3 flex items-start gap-4">
                        {{-- Action badge --}}
                        @php
                            $badgeClass = match($log->action) {
                                'created' => 'bg-emerald-50 text-emerald-700',
                                'updated' => 'bg-amber-50 text-amber-700',
                                'deleted' => 'bg-rose-50 text-rose-700',
                                default   => 'bg-slate-100 text-slate-500',
                            };
                        @endphp
                        <span class="mt-0.5 shrink-0 inline-flex px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wide {{ $badgeClass }}">
                            {{ $log->action }}
                        </span>

                        <div class="flex-1 min-w-0">
                            <p class="text-xs text-slate-500">
                                <span class="font-semibold text-slate-700">{{ $log->user?->name ?? 'System' }}</span>
                                · {{ $log->created_at->format('d M Y, H:i') }}
                                @if ($log->ip_address)
                                    · <span class="font-mono text-[10px] text-slate-400">{{ $log->ip_address }}</span>
                                @endif
                            </p>

                            {{-- Changed fields diff --}}
                            @if ($log->action === 'updated' && $log->new_values)
                                <div class="mt-1.5 flex flex-wrap gap-1.5">
                                    @foreach ($log->new_values as $field => $newVal)
                                        @php $oldVal = $log->old_values[$field] ?? null; @endphp
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-slate-50 border border-slate-200 text-[10px] text-slate-600">
                                            <span class="font-medium">{{ str_replace('_', ' ', $field) }}</span>:
                                            @if ($oldVal !== null)
                                                <span class="line-through text-rose-400">{{ is_array($oldVal) ? json_encode($oldVal) : $oldVal }}</span>
                                                →
                                            @endif
                                            <span class="text-emerald-700 font-semibold">{{ is_array($newVal) ? json_encode($newVal) : $newVal }}</span>
                                        </span>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
    @endcan

</div>
@endsection
