@extends('layouts.app')

@section('title', 'Dashboard')
@section('page_title', 'Dashboard')
@section('page_header', 'Weekly payroll overview')
@section('page_subtitle')
Year {{ $currentYear }} - Week {{ $currentWeek }} <span class="mx-1 text-slate-300">|</span> {{ $today->startOfWeek()->format('d M') }} to {{ $today->copy()->endOfWeek()->format('d M') }}
@endsection
@section('page_action')
    @if (($attendanceStats['daily_locked'] ?? false) || ($attendanceStats['hourly_locked'] ?? false))
        <div class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-emerald-50 text-emerald-700 border border-emerald-100 text-xs font-medium shadow-sm">
            <span class="inline-block w-2 h-2 rounded-full bg-emerald-500"></span>
            <span>Locked for @if ($attendanceStats['daily_locked'] ?? false) daily @endif @if (($attendanceStats['daily_locked'] ?? false) && ($attendanceStats['hourly_locked'] ?? false)) and @endif @if ($attendanceStats['hourly_locked'] ?? false) hourly @endif</span>
        </div>
    @else
        <div class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-amber-50 text-amber-700 border border-amber-100 text-xs font-medium shadow-sm">
            <span class="inline-block w-2 h-2 rounded-full bg-amber-500 shadow-[0_0_8px_rgba(245,158,11,0.6)]"></span>
            <span>Attendance editable</span>
        </div>
    @endif
@endsection

@section('content')
    <div class="space-y-6" x-data="{}">



        {{-- Top metrics cards --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-5">

            @if (auth()->user()->role === 'admin')
                {{-- Employees --}}
                <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4 hover:shadow-md transition duration-300 flex flex-col justify-between">
                    <div class="flex justify-between items-start mb-2">
                        <h2 class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Total Employees</h2>
                        <span class="inline-flex px-1.5 py-0.5 rounded text-[10px] bg-emerald-50 border border-emerald-100 text-emerald-700 font-bold tracking-wide">ACTIVE: {{ $employeeStats['active'] ?? 0 }}</span>
                    </div>
                    <div class="flex items-end gap-3 mt-1">
                        <p class="text-3xl font-bold text-slate-800 leading-none">{{ $employeeStats['total'] ?? 0 }}</p>
                        <div class="flex flex-col text-[10px] text-slate-400 font-medium pb-0.5">
                            <span>Daily focus: {{ $employeeStats['daily'] ?? 0 }}</span>
                            <span>Hourly focus: {{ $employeeStats['hourly'] ?? 0 }}</span>
                        </div>
                    </div>
                </div>
            @endif

            {{-- Attendance this week --}}
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4 hover:shadow-md transition duration-300 flex flex-col justify-between">
                <div class="flex justify-between items-start mb-2">
                    <h2 class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Daily Presence</h2>
                    <div class="flex items-center gap-1.5">
                        <span class="px-1.5 py-0.5 rounded border text-[9px] font-bold uppercase {{ ($attendanceStats['daily_locked'] ?? false) ? 'bg-slate-50 border-slate-200 text-slate-500' : 'bg-amber-50 border-amber-200 text-amber-700' }}">D: {{ ($attendanceStats['daily_locked'] ?? false) ? 'LK' : 'OP' }}</span>
                        <span class="px-1.5 py-0.5 rounded border text-[9px] font-bold uppercase {{ ($attendanceStats['hourly_locked'] ?? false) ? 'bg-slate-50 border-slate-200 text-slate-500' : 'bg-amber-50 border-amber-200 text-amber-700' }}">H: {{ ($attendanceStats['hourly_locked'] ?? false) ? 'LK' : 'OP' }}</span>
                    </div>
                </div>
                <div class="flex items-end gap-3 mt-1">
                    <p class="text-3xl font-bold text-slate-800 leading-none">{{ $attendanceStats['daily_present'] ?? 0 }}<span class="text-sm font-medium text-slate-400">/{{ $attendanceStats['daily_total'] ?? 0 }}</span></p>
                    <div class="flex flex-col text-[10px] text-slate-400 font-medium pb-0.5">
                        <span>{{ number_format($attendanceStats['hourly_hours'] ?? 0, 1) }}h total logged</span>
                        @if (($attendanceStats['hourly_ot'] ?? 0) > 0)
                            <span class="text-emerald-600 font-semibold">+{{ number_format($attendanceStats['hourly_ot'] ?? 0, 1) }}h OT</span>
                        @endif
                    </div>
                </div>
            </div>

            @if (auth()->user()->role === 'admin')
                {{-- Latest payroll --}}
                <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4 hover:shadow-md transition duration-300 flex flex-col justify-between">
                    <div class="flex justify-between items-start mb-2">
                        <h2 class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Latest Gross</h2>
                        @if ($payrollStats['has_run'])
                            <span class="px-1.5 py-0.5 rounded bg-slate-50 border border-slate-200 text-slate-600 text-[10px] font-bold tracking-wide">WK {{ $payrollStats['week'] }}/{{ substr($payrollStats['year'], -2) }}</span>
                        @else
                            <span class="px-1.5 py-0.5 rounded bg-slate-50 border border-slate-200 text-slate-400 text-[10px] font-bold tracking-wide">NO RUNS</span>
                        @endif
                    </div>
                    <div class="flex items-end gap-3 mt-1">
                        <p class="text-3xl font-bold text-slate-800 leading-none">
                            <span class="text-xl text-slate-400 mr-0.5 font-medium">€</span>{{ number_format($payrollStats['total_gross'] ?? 0) }}
                        </p>
                        <div class="flex flex-col text-[10px] text-slate-400 font-medium pb-0.5 uppercase tracking-wider">
                            <span><span class="inline-block w-1.5 h-1.5 rounded-full bg-emerald-500 mr-1"></span> CSH {{ number_format($payrollStats['total_cash'] ?? 0) }}</span>
                            <span><span class="inline-block w-1.5 h-1.5 rounded-full bg-blue-500 mr-1"></span> BNK {{ number_format($payrollStats['total_bank'] ?? 0) }}</span>
                        </div>
                    </div>
                </div>
            @endif

        </div>

        @if (auth()->user()->role === 'admin')
            {{-- Quick actions + report card --}}
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 pt-6">

                {{-- Quick actions --}}
                <div class="lg:col-span-2">
                    <div class="flex items-center justify-between mb-4 mt-2">
                        <h2 class="text-lg font-bold text-slate-800 tracking-tight">Quick Actions</h2>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

                        <a href="{{ route('employees.index') }}"
                            class="group flex items-center justify-between p-4 rounded-2xl border border-slate-200 bg-white hover:border-emerald-500 hover:shadow-md hover:shadow-emerald-500/10 transition duration-300">
                            <div class="flex items-center gap-4">
                                <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center border border-emerald-100">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                                </div>
                                <div>
                                    <h3 class="font-semibold text-slate-800 tracking-tight">Add Employee</h3>
                                    <p class="text-[11px] text-slate-500 mt-0.5">Create new staff record</p>
                                </div>
                            </div>
                            <svg class="w-4 h-4 text-slate-300 group-hover:text-emerald-500 transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                        </a>

                        <a href="{{ route('attendance.index', ['tab' => 'daily']) }}"
                            class="group flex items-center justify-between p-4 rounded-2xl border border-slate-200 bg-white hover:border-emerald-500 hover:shadow-md hover:shadow-emerald-500/10 transition duration-300">
                            <div class="flex items-center gap-4">
                                <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center border border-emerald-100">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V4m8 3V4M4 11h16M6 5h12a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2Z"/></svg>
                                </div>
                                <div>
                                    <h3 class="font-semibold text-slate-800 tracking-tight">Daily Attendance</h3>
                                    <p class="text-[11px] text-slate-500 mt-0.5">Log presence for CTC staff</p>
                                </div>
                            </div>
                            <svg class="w-4 h-4 text-slate-300 group-hover:text-emerald-500 transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                        </a>

                        <a href="{{ route('attendance.index', ['tab' => 'hourly']) }}"
                            class="group flex items-center justify-between p-4 rounded-2xl border border-slate-200 bg-white hover:border-blue-500 hover:shadow-md hover:shadow-blue-500/10 transition duration-300">
                            <div class="flex items-center gap-4">
                                <div class="w-10 h-10 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center border border-blue-100">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                </div>
                                <div>
                                    <h3 class="font-semibold text-slate-800 tracking-tight">Hourly Logs</h3>
                                    <p class="text-[11px] text-slate-500 mt-0.5">Record hours and OT</p>
                                </div>
                            </div>
                            <svg class="w-4 h-4 text-slate-300 group-hover:text-blue-500 transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                        </a>

                        <a href="{{ route('payroll.index', ['year' => $currentYear, 'week' => $currentWeek]) }}"
                            class="group flex items-center justify-between p-4 rounded-2xl border border-slate-200 bg-white hover:border-slate-800 hover:shadow-md hover:shadow-slate-800/10 transition duration-300">
                            <div class="flex items-center gap-4">
                                <div class="w-10 h-10 rounded-xl bg-slate-50 text-slate-700 flex items-center justify-center border border-slate-100">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                </div>
                                <div>
                                    <h3 class="font-semibold text-slate-800 tracking-tight">Weekly Payroll</h3>
                                    <p class="text-[11px] text-slate-500 mt-0.5">Review gross amounts</p>
                                </div>
                            </div>
                            <svg class="w-4 h-4 text-slate-300 group-hover:text-slate-800 transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                        </a>

                    </div>
                </div>

                {{-- Reports --}}
                <div>
                    <div class="flex items-center justify-between mb-4 mt-2">
                        <h2 class="text-lg font-bold text-slate-800 tracking-tight">System Info</h2>
                    </div>

                    <div class="bg-white rounded-2xl border border-slate-200/50 p-6 space-y-4 shadow-sm">
                        <a href="{{ route('payroll.weekly.report', ['year' => $currentYear, 'week' => $currentWeek]) }}"
                            class="group flex flex-col items-start gap-1 rounded-xl border border-slate-100 bg-slate-50 px-4 py-3 hover:border-slate-300 hover:bg-slate-100 transition">
                            <p class="font-bold tracking-tight text-slate-800 text-[13px] group-hover:text-emerald-700 transition">View Attendance Report</p>
                            <p class="text-[11px] text-slate-500">
                                Detailed PDF view of days and hours.
                            </p>
                        </a>

                        @if ($payrollStats['has_run'])
                            <div class="rounded-xl border border-slate-100 bg-white px-4 py-3 border-l-4 border-l-emerald-500">
                                <p class="text-[9px] font-bold text-slate-400 uppercase tracking-wide mb-1">
                                    Latest snapshot (W{{ $payrollStats['week'] }}/{{ $payrollStats['year'] }})
                                </p>
                                <p class="text-[11px] text-slate-600">
                                    Saved with <span class="font-bold text-slate-900 pb-0.5">€{{ number_format($payrollStats['total_gross'] ?? 0) }}</span> total gross split into cash/bank.
                                </p>
                            </div>
                        @else
                            <div class="rounded-xl border border-dashed border-slate-200 bg-slate-50 px-4 py-3">
                                <p class="text-[11px] text-slate-500">
                                    Summary will appear when payroll is run.
                                </p>
                            </div>
                        @endif
                    </div>
                </div>

            </div>
        @endif

        @if (auth()->user()->role === 'admin')
            {{-- Recent payroll runs history ──────────────────────────────── --}}
            <div class="mt-8 pt-4">
                <div class="flex items-center justify-between mb-5">
                    <h2 class="text-lg font-bold text-slate-800 tracking-tight">Recent Payroll Runs</h2>
                    <a href="{{ route('payroll.index', ['year' => $currentYear, 'week' => $currentWeek]) }}"
                        class="text-[11px] font-bold text-emerald-700 bg-emerald-50 hover:bg-emerald-100 px-3 py-1.5 rounded-full transition duration-300 inline-flex items-center gap-1">
                        VIEW CURRENT W{{ $currentWeek }}
                        <svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                        </svg>
                    </a>
                </div>

                <div class="bg-white rounded-xl border border-slate-200/60 shadow-[0_2px_10px_-3px_rgba(0,0,0,0.02)] overflow-hidden">
                @if ($recentRuns->isEmpty())
                    <div class="px-5 py-8 text-center text-sm text-slate-400">
                        No payroll runs saved yet. Run your first weekly payroll to see history here.
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-xs">
                            <thead class="bg-slate-50 text-slate-500 uppercase tracking-wide text-[11px] font-semibold">
                                <tr>
                                    <th class="px-5 py-3 text-left">Week</th>
                                    <th class="px-5 py-3 text-left">Status</th>
                                    <th class="px-5 py-3 text-right">Employees</th>
                                    <th class="px-5 py-3 text-right">Gross</th>
                                    <th class="px-5 py-3 text-right">Cash</th>
                                    <th class="px-5 py-3 text-right">Bank</th>
                                    <th class="px-5 py-3 text-right"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach ($recentRuns as $run)
                                    <tr class="hover:bg-slate-50/70 transition">
                                        <td class="px-5 py-3 font-medium text-slate-800">
                                            W{{ $run['week'] }}/{{ $run['year'] }}
                                        </td>
                                        <td class="px-5 py-3">
                                            @if ($run['status'] === 'final')
                                                <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-100">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Final
                                                </span>
                                            @else
                                                <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full bg-amber-50 text-amber-700 border border-amber-100">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-amber-400"></span> Draft
                                                </span>
                                            @endif
                                        </td>
                                        <td class="px-5 py-3 text-right text-slate-600">{{ $run['employees'] }}</td>
                                        <td class="px-5 py-3 text-right font-semibold text-slate-800">
                                            {{ number_format($run['total_gross'], 2) }}
                                        </td>
                                        <td class="px-5 py-3 text-right text-emerald-700">
                                            {{ number_format($run['total_cash'], 2) }}
                                        </td>
                                        <td class="px-5 py-3 text-right text-blue-700">
                                            {{ number_format($run['total_bank'], 2) }}
                                        </td>
                                        <td class="px-5 py-3 text-right">
                                            <a href="{{ route('payroll.index', ['year' => $run['year'], 'week' => $run['week']]) }}"
                                                class="inline-flex items-center gap-1 text-slate-400 hover:text-slate-700 transition">
                                                View
                                                <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                                                </svg>
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
            </div>
        @endif

    </div>
@endsection

