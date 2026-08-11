@extends('layouts.app')

@section('title', $employee->name . ' — Profile')
@section('page_title', 'Employee Profile')
@section('page_header', $employee->name)
@section('page_subtitle', $employee->employee_code . ($employee->department ? ' · ' . $employee->department : ''))
@section('page_action')
    <div class="flex items-center gap-3">
        @can('update', $employee)
            <a href="{{ route('employees.edit', $employee) }}"
                class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-slate-900 text-white text-sm font-medium hover:bg-slate-800 transition shadow-sm">
                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 3.487a1.5 1.5 0 0 1 2.121 0l1.53 1.53a1.5 1.5 0 0 1 0 2.122l-10.01 10.01-4.243.707.707-4.243 10-10.126Z" />
                </svg>
                Edit Employee
            </a>
        @endcan
        <a href="{{ route('employees.index') }}"
            class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg border border-slate-200 bg-white text-sm text-slate-700 hover:bg-slate-50 transition">
            ← Back to Employees
        </a>
    </div>
@endsection

@section('content')
@php
    // Rates in effect today; a future-dated entry is scheduled, not current.
    $isDaily = $employee->type === 'daily_rate';
    $upcomingLabel = function ($entry, bool $hours) {
        if (! $entry) {
            return null;
        }
        $amount = $hours ? number_format($entry->amount, 1) . ' hrs' : '€' . number_format($entry->amount, 2);

        return $amount . ' from ' . \Carbon\Carbon::parse($entry->effective_from)->format('d M Y');
    };

    $latestPayroll = $payrollHistory->first();
    $bankLine = $employee->bank_name || $employee->bank_account
        ? trim(($employee->bank_name ?? '') . ' · ' . ($employee->bank_account ?? ''), ' ·')
        : null;
@endphp

<div class="space-y-5">

    {{-- ── Identity + attributes ─────────────────────────────────────────────
         The name, code and department already sit in the page header above, so
         this strip carries only what is not repeated there. --}}
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm px-5 py-4">
        <div class="flex flex-wrap items-center gap-x-8 gap-y-4">

            <div class="flex items-center gap-3 shrink-0">
                <div class="relative">
                    <div class="h-11 w-11 rounded-xl bg-gradient-to-br from-slate-800 to-slate-900 text-white flex items-center justify-center text-base font-bold">
                        {{ strtoupper(mb_substr($employee->name, 0, 1)) }}
                    </div>
                    <span class="absolute -bottom-0.5 -right-0.5 w-3.5 h-3.5 rounded-full border-2 border-white {{ $employee->is_active ? 'bg-emerald-500' : 'bg-rose-400' }}"></span>
                </div>
                <div class="flex flex-col gap-1">
                    <span class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-medium {{ $isDaily ? 'bg-violet-50 text-violet-700' : 'bg-brand-50 text-brand-700' }}">
                        {{ $isDaily ? 'Daily Rate' : 'Hourly' }}
                    </span>
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-medium {{ $employee->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700' }}">
                        <span class="w-1.5 h-1.5 rounded-full {{ $employee->is_active ? 'bg-emerald-500' : 'bg-rose-400' }}"></span>
                        {{ $employee->is_active ? 'Active' : 'Inactive' }}
                    </span>
                </div>
            </div>

            <div class="h-10 w-px bg-slate-100 hidden sm:block"></div>

            {{-- Attribute pairs, wrapping naturally instead of each owning a card --}}
            <dl class="flex flex-wrap gap-x-8 gap-y-3 text-sm">
                <div>
                    <dt class="text-[11px] font-medium uppercase tracking-wide text-slate-400">Joined</dt>
                    <dd class="text-slate-800 mt-0.5">{{ $employee->joining_date?->format('d M Y') ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-[11px] font-medium uppercase tracking-wide text-slate-400">Weekly Days</dt>
                    <dd class="text-slate-800 mt-0.5">{{ $employee->weekly_active_days ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-[11px] font-medium uppercase tracking-wide text-slate-400">Bank Transfer Fix</dt>
                    <dd class="text-slate-800 mt-0.5">
                        {{ $employee->bank_transfer_fix_amount ? '€' . number_format($employee->bank_transfer_fix_amount, 2) : '—' }}
                    </dd>
                </div>
                <div class="min-w-0">
                    <dt class="text-[11px] font-medium uppercase tracking-wide text-slate-400">Bank Account</dt>
                    <dd class="text-slate-800 mt-0.5 truncate">{{ $bankLine ?? '—' }}</dd>
                </div>
                @if (! $employee->is_active && $employee->deactivated_at)
                    <div>
                        <dt class="text-[11px] font-medium uppercase tracking-wide text-slate-400">Deactivated</dt>
                        <dd class="text-rose-700 mt-0.5">{{ $employee->deactivated_at->format('d M Y') }}</dd>
                    </div>
                @endif
            </dl>
        </div>
    </div>

    {{-- ── Pay figures — one card per genuinely distinct number, sized so the
           row always fills evenly (2 for daily, 3 for hourly) ──────────────── --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 {{ $isDaily ? '' : 'lg:grid-cols-3' }} gap-4">
        @if ($isDaily)
            @php
                $rate = $employee->currentRateOf('daily_rate');
                $upcoming = $upcomingLabel($employee->upcomingRateEntryOf('daily_rate'), false);
            @endphp
            <div class="bg-white rounded-xl border border-slate-200 border-l-4 border-l-brand-500 shadow-sm px-5 py-4">
                <p class="text-[11px] font-medium uppercase tracking-wide text-slate-400">Current Daily Rate</p>
                <p class="text-2xl font-bold text-slate-900 mt-1">€{{ number_format($rate, 2) }}</p>
                <p class="text-xs mt-1 {{ $upcoming ? 'text-sky-700' : 'text-slate-400' }}">
                    {{ $upcoming ? '→ ' . $upcoming : 'per day' }}
                </p>
            </div>
        @else
            @php
                $hourlyRate = $employee->currentRateOf('hourly_rate');
                $hoursPerDay = $employee->currentRateOf('hours_per_day');
                $upcomingHourly = $upcomingLabel($employee->upcomingRateEntryOf('hourly_rate'), false);
                $upcomingHours = $upcomingLabel($employee->upcomingRateEntryOf('hours_per_day'), true);
            @endphp
            <div class="bg-white rounded-xl border border-slate-200 border-l-4 border-l-brand-500 shadow-sm px-5 py-4">
                <p class="text-[11px] font-medium uppercase tracking-wide text-slate-400">Current Hourly Rate</p>
                <p class="text-2xl font-bold text-slate-900 mt-1">€{{ number_format($hourlyRate, 2) }}</p>
                <p class="text-xs mt-1 {{ $upcomingHourly ? 'text-sky-700' : 'text-slate-400' }}">
                    {{ $upcomingHourly ? '→ ' . $upcomingHourly : 'per hour' }}
                </p>
            </div>
            <div class="bg-white rounded-xl border border-slate-200 border-l-4 border-l-sky-400 shadow-sm px-5 py-4">
                <p class="text-[11px] font-medium uppercase tracking-wide text-slate-400">Hours / Day</p>
                <p class="text-2xl font-bold text-slate-900 mt-1">{{ number_format($hoursPerDay, 1) }}</p>
                <p class="text-xs mt-1 {{ $upcomingHours ? 'text-sky-700' : 'text-slate-400' }}">
                    {{ $upcomingHours ? '→ ' . $upcomingHours : 'standard hours' }}
                </p>
            </div>
        @endif

        <div class="bg-white rounded-xl border border-slate-200 border-l-4 border-l-emerald-500 shadow-sm px-5 py-4">
            <p class="text-[11px] font-medium uppercase tracking-wide text-slate-400">Latest Payroll</p>
            @if ($latestPayroll)
                @php $latestRun = $latestPayroll->payrollRun; @endphp
                <p class="text-2xl font-bold text-slate-900 mt-1">€{{ number_format($latestPayroll->gross_amount, 2) }}</p>
                <p class="text-xs text-slate-400 mt-1">
                    {{ $latestRun->period_type === 'weekly' ? 'W' . $latestRun->week_number . '/' . $latestRun->year : $latestRun->month }}
                    <span class="{{ $latestRun->status === 'final' ? 'text-emerald-600' : 'text-amber-600' }} font-medium">
                        · {{ ucfirst($latestRun->status) }}
                    </span>
                </p>
            @else
                <p class="text-2xl font-bold text-slate-300 mt-1">—</p>
                <p class="text-xs text-slate-400 mt-1">No payroll yet</p>
            @endif
        </div>
    </div>

    {{-- ── Attendance & Payroll — both fixed-length (8 and 6 rows), so they can
           render in full side by side without ever growing the page ────────── --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5 items-start">

        <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="px-5 py-3 border-b border-slate-100 flex items-center gap-2">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                <h2 class="text-sm font-semibold text-slate-800">Attendance</h2>
                <span class="text-xs text-slate-400">— last 8 weeks</span>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full text-[13px]">
                    <thead class="bg-slate-50 text-slate-500 uppercase text-[11px] font-semibold tracking-wide">
                        <tr>
                            <th class="px-5 py-2.5 text-left">Week</th>
                            @if ($attendanceHistory->first()['type'] === 'daily')
                                <th class="px-5 py-2.5 text-right">Present</th>
                                <th class="px-5 py-2.5 text-right">Working</th>
                                <th class="px-5 py-2.5 text-right">OT (€)</th>
                            @else
                                <th class="px-5 py-2.5 text-right">Hours</th>
                                <th class="px-5 py-2.5 text-right">OT Hrs</th>
                            @endif
                            <th class="px-5 py-2.5 text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($attendanceHistory as $i => $week)
                            <tr class="hover:bg-slate-50/60 transition {{ ! $week['marked'] ? 'text-slate-400' : '' }} {{ $i === 0 && $week['marked'] ? 'bg-emerald-50/30' : '' }}">
                                <td class="px-5 py-2.5 font-medium {{ $week['marked'] ? 'text-slate-700' : '' }}">
                                    W{{ $week['week'] }}/{{ $week['year'] }}
                                    @if ($i === 0)
                                        <span class="ml-1 text-[10px] font-semibold uppercase tracking-wide text-slate-400">Now</span>
                                    @endif
                                </td>
                                @if ($week['type'] === 'daily')
                                    <td class="px-5 py-2.5 text-right font-semibold {{ $week['marked'] ? 'text-slate-800' : '' }}">{{ $week['marked'] ? $week['present'] : '—' }}</td>
                                    <td class="px-5 py-2.5 text-right text-slate-500">{{ $week['marked'] ? $week['total'] : '—' }}</td>
                                    <td class="px-5 py-2.5 text-right {{ $week['marked'] && $week['overtime'] > 0 ? 'text-emerald-700' : '' }}">
                                        {{ $week['marked'] && $week['overtime'] > 0 ? '€' . number_format($week['overtime'], 2) : '—' }}
                                    </td>
                                @else
                                    <td class="px-5 py-2.5 text-right font-semibold {{ $week['marked'] ? 'text-slate-800' : '' }}">{{ $week['marked'] ? number_format($week['total_hours'], 1) : '—' }}</td>
                                    <td class="px-5 py-2.5 text-right {{ $week['marked'] && $week['overtime_hours'] > 0 ? 'text-emerald-700' : '' }}">
                                        {{ $week['marked'] && $week['overtime_hours'] > 0 ? number_format($week['overtime_hours'], 1) : '—' }}
                                    </td>
                                @endif
                                <td class="px-5 py-2.5 text-center">
                                    @if (! $week['marked'])
                                        <span class="inline-flex px-2 py-0.5 rounded-full text-[11px] bg-slate-100 text-slate-400">Not marked</span>
                                    @elseif ($week['locked'])
                                        <span class="inline-flex px-2 py-0.5 rounded-full text-[11px] bg-slate-100 text-slate-500">Locked</span>
                                    @else
                                        <span class="inline-flex px-2 py-0.5 rounded-full text-[11px] bg-emerald-50 text-emerald-700">Open</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="px-5 py-3 border-b border-slate-100 flex items-center gap-2">
                <span class="w-1.5 h-1.5 rounded-full bg-brand-500"></span>
                <h2 class="text-sm font-semibold text-slate-800">Payroll</h2>
                <span class="text-xs text-slate-400">— last 6 runs</span>
            </div>

            @if ($payrollHistory->isEmpty())
                <div class="px-5 py-10 text-center text-sm text-slate-400">No payroll history recorded.</div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full text-[13px]">
                        <thead class="bg-slate-50 text-slate-500 uppercase text-[11px] font-semibold tracking-wide">
                            <tr>
                                <th class="px-5 py-2.5 text-left">Period</th>
                                <th class="px-5 py-2.5 text-left">Status</th>
                                <th class="px-5 py-2.5 text-right">Gross</th>
                                <th class="px-5 py-2.5 text-right">Cash</th>
                                <th class="px-5 py-2.5 text-right">Bank</th>
                                <th class="px-5 py-2.5 text-right">Advance</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($payrollHistory as $i => $item)
                                @php $run = $item->payrollRun; @endphp
                                <tr class="hover:bg-slate-50/60 transition {{ $i === 0 ? 'bg-emerald-50/30' : '' }}">
                                    <td class="px-5 py-2.5 font-medium text-slate-700">
                                        {{ $run->period_type === 'weekly' ? 'W' . $run->week_number . '/' . $run->year : $run->month }}
                                    </td>
                                    <td class="px-5 py-2.5">
                                        @if ($run->status === 'final')
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] bg-emerald-50 text-emerald-700">
                                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Final
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] bg-amber-50 text-amber-700">
                                                <span class="w-1.5 h-1.5 rounded-full bg-amber-400"></span> Draft
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-2.5 text-right font-semibold text-slate-800">€{{ number_format($item->gross_amount, 2) }}</td>
                                    <td class="px-5 py-2.5 text-right text-emerald-700">€{{ number_format($item->cash_amount, 2) }}</td>
                                    <td class="px-5 py-2.5 text-right text-brand-700">€{{ number_format($item->bank_amount, 2) }}</td>
                                    <td class="px-5 py-2.5 text-right {{ ($item->advance_given ?? 0) > 0 ? 'text-rose-600 font-semibold' : 'text-slate-300' }}">
                                        {{ ($item->advance_given ?? 0) > 0 ? '€' . number_format($item->advance_given, 2) : '—' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    {{-- ── Salary history — grows without limit over an employee's life, so it
           scrolls inside a fixed frame rather than stretching the page ─────── --}}
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-5 py-3 border-b border-slate-100 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span class="w-1.5 h-1.5 rounded-full bg-violet-500"></span>
                <h2 class="text-sm font-semibold text-slate-800">Salary Change History</h2>
            </div>
            <span class="text-xs text-slate-400">{{ $rateHistory->count() }} {{ Str::plural('entry', $rateHistory->count()) }}</span>
        </div>

        @if ($rateHistory->isEmpty())
            <div class="px-5 py-10 text-center text-sm text-slate-400">No rate changes recorded yet.</div>
        @else
            <div class="overflow-auto max-h-80">
                <table class="min-w-full text-[13px]">
                    <thead class="bg-slate-50 text-slate-500 uppercase text-[11px] font-semibold tracking-wide sticky top-0">
                        <tr>
                            <th class="px-5 py-2.5 text-left">Rate Type</th>
                            <th class="px-5 py-2.5 text-right">Amount</th>
                            <th class="px-5 py-2.5 text-left">Effective From</th>
                            <th class="px-5 py-2.5 text-left">Effective To</th>
                            <th class="px-5 py-2.5 text-left">Changed By</th>
                            <th class="px-5 py-2.5 text-left">Recorded On</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @php
                            // Which entry is in effect today, per rate type — the newest row
                            // may be future-dated and therefore only scheduled.
                            $currentEntryIds = $rateHistory->pluck('rate_type')->unique()
                                ->map(fn ($type) => $employee->rateEntryAt($type)?->id)
                                ->filter()->all();
                        @endphp
                        @foreach ($rateHistory as $rate)
                            @php
                                $isCurrentRate = in_array($rate->id, $currentEntryIds, true);
                                $isScheduledRate = $rate->effective_from
                                    && \Carbon\Carbon::parse($rate->effective_from)->toDateString() > now()->toDateString();

                                $typeLabel = match ($rate->rate_type) {
                                    'daily_rate' => 'Daily Rate',
                                    'hourly_rate' => 'Hourly Rate',
                                    'hours_per_day' => 'Hours / Day',
                                    default => $rate->rate_type,
                                };
                                $typeBadge = match ($rate->rate_type) {
                                    'daily_rate' => 'bg-violet-50 text-violet-700',
                                    'hourly_rate' => 'bg-brand-50 text-brand-700',
                                    default => 'bg-slate-100 text-slate-600',
                                };
                            @endphp
                            <tr class="hover:bg-slate-50/60 transition {{ $isCurrentRate ? 'bg-emerald-50/30' : '' }}">
                                <td class="px-5 py-2.5 whitespace-nowrap">
                                    <span class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-medium {{ $typeBadge }}">{{ $typeLabel }}</span>
                                    @if ($isCurrentRate)
                                        <span class="ml-1.5 inline-flex px-1.5 py-0.5 rounded text-[10px] font-bold uppercase tracking-wide bg-emerald-100 text-emerald-700">Current</span>
                                    @elseif ($isScheduledRate)
                                        <span class="ml-1.5 inline-flex px-1.5 py-0.5 rounded text-[10px] font-bold uppercase tracking-wide bg-sky-100 text-sky-700">Scheduled</span>
                                    @endif
                                </td>
                                <td class="px-5 py-2.5 text-right font-mono font-semibold text-slate-800 whitespace-nowrap">
                                    {{ $rate->rate_type === 'hours_per_day' ? number_format($rate->amount, 1) : '€' . number_format($rate->amount, 2) }}
                                </td>
                                <td class="px-5 py-2.5 text-slate-600 whitespace-nowrap">
                                    {{ $rate->effective_from ? \Carbon\Carbon::parse($rate->effective_from)->format('d M Y') : '—' }}
                                </td>
                                <td class="px-5 py-2.5 text-slate-500 whitespace-nowrap">
                                    {{ $rate->effective_to ? \Carbon\Carbon::parse($rate->effective_to)->format('d M Y') : 'Current' }}
                                </td>
                                <td class="px-5 py-2.5 text-slate-600">{{ $creators->get($rate->created_by, '—') }}</td>
                                <td class="px-5 py-2.5 text-slate-400 whitespace-nowrap">
                                    {{ $rate->created_at?->format('d M Y, H:i') ?? '—' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- ── Audit trail — up to 25 entries, each with a variable number of field
           chips, so it also scrolls inside a fixed frame ──────────────────── --}}
    @can('update', $employee)
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-5 py-3 border-b border-slate-100 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>
                <h2 class="text-sm font-semibold text-slate-800">Audit Trail</h2>
            </div>
            <span class="text-xs text-slate-400">{{ $auditLogs->count() }} recent {{ Str::plural('change', $auditLogs->count()) }}</span>
        </div>

        @if ($auditLogs->isEmpty())
            <div class="px-5 py-10 text-center text-sm text-slate-400">No audit records found.</div>
        @else
            <div class="divide-y divide-slate-100 overflow-auto max-h-96">
                @foreach ($auditLogs as $log)
                    @php
                        $badgeClass = match ($log->action) {
                            'created' => 'bg-emerald-50 text-emerald-700',
                            'updated' => 'bg-amber-50 text-amber-700',
                            'deleted' => 'bg-rose-50 text-rose-700',
                            default => 'bg-slate-100 text-slate-500',
                        };
                    @endphp
                    <div class="px-5 py-2.5 flex items-start gap-3">
                        <span class="mt-0.5 shrink-0 inline-flex px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wide {{ $badgeClass }}">
                            {{ $log->action }}
                        </span>

                        <div class="flex-1 min-w-0">
                            <p class="text-xs text-slate-500">
                                <span class="font-semibold text-slate-700">{{ $log->user?->name ?? 'System' }}</span>
                                · {{ $log->created_at->format('d M Y, H:i') }}
                            </p>

                            @if ($log->action === 'updated' && $log->new_values)
                                <div class="mt-1 flex flex-wrap gap-1">
                                    @foreach ($log->new_values as $field => $newVal)
                                        @php $oldVal = $log->old_values[$field] ?? null; @endphp
                                        <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded bg-slate-50 border border-slate-200 text-[11px] text-slate-600">
                                            <span class="font-medium">{{ str_replace('_', ' ', $field) }}</span>
                                            @if ($oldVal !== null)
                                                <span class="line-through text-slate-400">{{ is_array($oldVal) ? json_encode($oldVal) : $oldVal }}</span>
                                                <span class="text-slate-300">→</span>
                                            @endif
                                            <span class="text-slate-800 font-medium">{{ is_array($newVal) ? json_encode($newVal) : $newVal }}</span>
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
