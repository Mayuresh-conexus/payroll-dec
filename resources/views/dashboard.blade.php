@extends('layouts.app')

@section('title', 'Dashboard')
@section('page_title', 'Dashboard')

@section('content')
    <div class="space-y-6" x-data="{}">

        {{-- Top intro + quick week info --}}
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold text-slate-900">
                    Weekly payroll overview
                </h1>
                <p class="mt-1 text-sm text-slate-500">
                    Year {{ $currentYear }} - Week {{ $currentWeek }},
                    {{ $today->startOfWeek()->format('d M') }}
                    to
                    {{ $today->copy()->endOfWeek()->format('d M') }}.
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-3 text-xs">

                @if (($attendanceStats['daily_locked'] ?? false) || ($attendanceStats['hourly_locked'] ?? false))
                    <div
                        class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-100">
                        <span class="inline-block w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                        <span>
                            Attendance locked for
                            @if ($attendanceStats['daily_locked'] ?? false)
                                daily
                            @endif
                            @if (($attendanceStats['daily_locked'] ?? false) && ($attendanceStats['hourly_locked'] ?? false))
                                and
                            @endif
                            @if ($attendanceStats['hourly_locked'] ?? false)
                                hourly
                            @endif
                            staff
                        </span>
                    </div>
                @else
                    <div
                        class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-amber-50 text-amber-700 border border-amber-100">
                        <span class="inline-block w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                        <span>Attendance still editable this week</span>
                    </div>
                @endif
            </div>
        </div>

        {{-- Top metrics cards --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">

            {{-- Employees --}}
            <div class="relative overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-sm">
                <div
                    class="absolute inset-0 pointer-events-none opacity-40 bg-gradient-to-br from-slate-50 via-slate-50 to-slate-100">
                </div>
                <div class="relative p-4 sm:p-5 space-y-4">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="text-xs font-medium tracking-wide text-slate-500 uppercase">
                                Employees
                            </p>
                            <p class="mt-1 text-2xl font-semibold text-slate-900">
                                {{ $employeeStats['active'] ?? 0 }}
                                <span class="ml-1 text-sm font-normal text-slate-500">active</span>
                            </p>
                        </div>
                        <div
                            class="shrink-0 w-10 h-10 rounded-xl bg-slate-900 text-slate-50 flex items-center justify-center">
                            {{-- people icon --}}
                            <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M16 16a4 4 0 0 0-8 0m-3 4a6 6 0 0 1 12 0M9 9a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm12 0a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm-3 11a6 6 0 0 0-5-5.917" />
                            </svg>
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-2 text-xs">
                        <div class="rounded-lg bg-slate-50 px-3 py-2">
                            <p class="text-slate-500">Total</p>
                            <p class="mt-0.5 font-semibold text-slate-800">
                                {{ $employeeStats['total'] ?? 0 }}
                            </p>
                        </div>
                        <div class="rounded-lg bg-emerald-50 px-3 py-2">
                            <p class="text-emerald-600">Daily</p>
                            <p class="mt-0.5 font-semibold text-emerald-700">
                                {{ $employeeStats['daily'] ?? 0 }}
                            </p>
                        </div>
                        <div class="rounded-lg bg-blue-50 px-3 py-2">
                            <p class="text-blue-600">Hourly</p>
                            <p class="mt-0.5 font-semibold text-blue-700">
                                {{ $employeeStats['hourly'] ?? 0 }}
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center justify-between text-xs text-slate-500 pt-1">
                        <span>Last updated {{ $today->format('d M Y') }}</span>
                        <a href="{{ route('employees.index') }}"
                            class="inline-flex items-center gap-1 text-slate-700 hover:text-slate-900">
                            Manage
                            <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor" stroke-width="1.7">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                            </svg>
                        </a>
                    </div>
                </div>
            </div>

            {{-- Attendance this week --}}
            <div class="relative overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-sm">
                <div
                    class="absolute inset-0 pointer-events-none opacity-40 bg-gradient-to-br from-emerald-50 via-slate-50 to-slate-100">
                </div>
                <div class="relative p-4 sm:p-5 space-y-4">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="text-xs font-medium tracking-wide text-slate-500 uppercase">
                                Attendance this week
                            </p>
                            <p class="mt-1 text-base text-slate-800">
                                Daily
                                @if ($attendanceStats['daily_locked'] ?? false)
                                    <span
                                        class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 text-xs">
                                        <span class="w-1 h-1 rounded-full bg-emerald-500"></span>
                                        locked
                                    </span>
                                @else
                                    <span
                                        class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-amber-50 text-amber-700 text-xs">
                                        <span class="w-1 h-1 rounded-full bg-amber-500"></span>
                                        open
                                    </span>
                                @endif
                                &nbsp;/ Hourly
                                @if ($attendanceStats['hourly_locked'] ?? false)
                                    <span
                                        class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 text-xs">
                                        <span class="w-1 h-1 rounded-full bg-emerald-500"></span>
                                        locked
                                    </span>
                                @else
                                    <span
                                        class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-amber-50 text-amber-700 text-xs">
                                        <span class="w-1 h-1 rounded-full bg-amber-500"></span>
                                        open
                                    </span>
                                @endif
                            </p>
                        </div>
                        <div
                            class="shrink-0 w-10 h-10 rounded-xl bg-emerald-600 text-emerald-50 flex items-center justify-center">
                            {{-- calendar icon --}}
                            <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M8 7V4m8 3V4M4 11h16M6 5h12a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2Z" />
                            </svg>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3 text-xs">
                        <div class="rounded-lg bg-white/80 border border-emerald-100 px-3 py-2">
                            <p class="text-slate-500">Daily staff presence</p>
                            <p class="mt-0.5 text-sm">
                                <span class="font-semibold text-slate-900">
                                    {{ $attendanceStats['daily_present'] ?? 0 }}
                                </span>
                                <span class="text-slate-400">
                                    of
                                    {{ $attendanceStats['daily_total'] ?? 0 }} days
                                </span>
                            </p>
                        </div>
                        <div class="rounded-lg bg-white/80 border border-blue-100 px-3 py-2">
                            <p class="text-slate-500">Hourly staff hours</p>
                            <p class="mt-0.5 text-sm">
                                <span class="font-semibold text-slate-900">
                                    {{ number_format($attendanceStats['hourly_hours'] ?? 0, 2) }} h
                                </span>
                                @if (($attendanceStats['hourly_ot'] ?? 0) > 0)
                                    <span class="ml-1 text-xs text-blue-600">
                                        + {{ number_format($attendanceStats['hourly_ot'] ?? 0, 2) }} OT
                                    </span>
                                @endif
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center justify-between text-xs text-slate-500 pt-1">
                        <span>Week {{ $currentWeek }} attendance</span>
                        <a href="{{ route('attendance.index') }}"
                            class="inline-flex items-center gap-1 text-slate-700 hover:text-slate-900">
                            Go to attendance
                            <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor" stroke-width="1.7">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                            </svg>
                        </a>
                    </div>
                </div>
            </div>

            {{-- Latest payroll --}}
            <div class="relative overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-sm">
                <div
                    class="absolute inset-0 pointer-events-none opacity-40 bg-gradient-to-br from-indigo-50 via-slate-50 to-slate-100">
                </div>
                <div class="relative p-4 sm:p-5 space-y-4">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="text-xs font-medium tracking-wide text-slate-500 uppercase">
                                Latest weekly payroll
                            </p>
                            @if ($payrollStats['has_run'])
                                <p class="mt-1 text-sm text-slate-600">
                                    Week {{ $payrollStats['week'] }}/{{ $payrollStats['year'] }}
                                </p>
                            @else
                                <p class="mt-1 text-sm text-slate-500">
                                    No payroll run saved yet.
                                </p>
                            @endif
                        </div>
                        <div
                            class="shrink-0 w-10 h-10 rounded-xl bg-slate-900 text-slate-50 flex items-center justify-center">
                            {{-- money icon --}}
                            <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M12 8c-2.5 0-3.5 3.5-1 4.5l2 .7c2.5 1 1.5 4.5-1 4.5-1.4 0-2.5-.8-3-2M12 6v2m0 10v-2M5 5h14a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2Z" />
                            </svg>
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-2 text-xs">
                        <div class="rounded-lg bg-white/80 border border-slate-100 px-3 py-2">
                            <p class="text-slate-500">Gross</p>
                            <p class="mt-0.5 font-semibold text-slate-900">
                                {{ number_format($payrollStats['total_gross'] ?? 0, 2) }}
                            </p>
                        </div>
                        <div class="rounded-lg bg-white/80 border border-emerald-100 px-3 py-2">
                            <p class="text-slate-500">Cash</p>
                            <p class="mt-0.5 font-semibold text-emerald-700">
                                {{ number_format($payrollStats['total_cash'] ?? 0, 2) }}
                            </p>
                        </div>
                        <div class="rounded-lg bg-white/80 border border-blue-100 px-3 py-2">
                            <p class="text-slate-500">Bank</p>
                            <p class="mt-0.5 font-semibold text-blue-700">
                                {{ number_format($payrollStats['total_bank'] ?? 0, 2) }}
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center justify-between text-xs text-slate-500 pt-1">
                        <span>View or adjust breakdown</span>
                        <a href="{{ route('payroll.index', ['year' => $currentYear, 'week' => $currentWeek]) }}"
                            class="inline-flex items-center gap-1 text-slate-700 hover:text-slate-900">
                            Open weekly payroll
                            <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none"
                                viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                            </svg>
                        </a>
                    </div>
                </div>
            </div>

        </div>

        {{-- Quick actions + report card --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

            {{-- Quick actions --}}
            <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-100 shadow-sm p-4 sm:p-5">
                <div class="flex items-center justify-between mb-3">
                    <h2 class="text-sm font-semibold text-slate-800">
                        Quick actions
                    </h2>
                    <span class="text-[11px] text-slate-400">
                        Most used flows in one tap.
                    </span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 text-xs">

                    <a href="{{ route('employees.index') }}"
                        class="group flex flex-col gap-1.5 rounded-xl border border-slate-100 bg-slate-50/60 px-3 py-3 hover:bg-slate-900 hover:text-slate-50 transition">
                        <div class="flex items-center justify-between">
                            <span class="font-medium">Add employee</span>
                            <span
                                class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-slate-900 text-slate-50 group-hover:bg-slate-100 group-hover:text-slate-900 text-sm">
                                +
                            </span>
                        </div>
                        <p class="text-[11px] text-slate-500 group-hover:text-slate-200">
                            Create a new staff record with daily or hourly rate.
                        </p>
                    </a>

                    <a href="{{ route('attendance.index', ['tab' => 'daily']) }}"
                        class="group flex flex-col gap-1.5 rounded-xl border border-emerald-100 bg-emerald-50/60 px-3 py-3 hover:bg-emerald-600 hover:text-emerald-50 transition">
                        <div class="flex items-center justify-between">
                            <span class="font-medium">Fill daily attendance</span>
                            <span
                                class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-emerald-600 text-emerald-50 group-hover:bg-emerald-100 group-hover:text-emerald-700 text-sm">
                                W
                            </span>
                        </div>
                        <p class="text-[11px] text-emerald-700 group-hover:text-emerald-100">
                            Mark presence for CTC staff for the current week.
                        </p>
                    </a>

                    <a href="{{ route('attendance.index', ['tab' => 'hourly']) }}"
                        class="group flex flex-col gap-1.5 rounded-xl border border-blue-100 bg-blue-50/60 px-3 py-3 hover:bg-blue-600 hover:text-blue-50 transition">
                        <div class="flex items-center justify-between">
                            <span class="font-medium">Fill hourly hours</span>
                            <span
                                class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-blue-600 text-blue-50 group-hover:bg-blue-100 group-hover:text-blue-700 text-sm">
                                H
                            </span>
                        </div>
                        <p class="text-[11px] text-blue-700 group-hover:text-blue-100">
                            Enter total and overtime hours for hourly staff.
                        </p>
                    </a>

                    <a href="{{ route('payroll.index', ['year' => $currentYear, 'week' => $currentWeek]) }}"
                        class="group flex flex-col gap-1.5 rounded-xl border border-slate-200 bg-white px-3 py-3 hover:bg-slate-900 hover:text-slate-50 transition">
                        <div class="flex items-center justify-between">
                            <span class="font-medium">Run weekly payroll</span>
                            <span
                                class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-slate-900 text-slate-50 group-hover:bg-slate-100 group-hover:text-slate-900 text-sm">
                                ₹
                            </span>
                        </div>
                        <p class="text-[11px] text-slate-500 group-hover:text-slate-200">
                            Review gross amounts and split between cash and bank.
                        </p>
                    </a>

                </div>
            </div>

            {{-- Reports --}}
            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4 sm:p-5 space-y-4">
                <div class="flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-slate-800">
                        Reports
                    </h2>
                </div>

                <div class="space-y-2 text-xs">
                    <a href="{{ route('payroll.weekly.report', ['year' => $currentYear, 'week' => $currentWeek]) }}"
                        class="flex items-center justify-between rounded-lg border border-slate-100 bg-slate-50/70 px-3 py-2.5 hover:bg-slate-900 hover:text-slate-50 transition">
                        <div>
                            <p class="font-medium">Weekly attendance report</p>
                            <p class="text-[11px] text-slate-500 group-hover:text-slate-200">
                                Detailed view of days and hours for the current week.
                            </p>
                        </div>
                        <svg class="w-4 h-4 text-slate-400 group-hover:text-slate-100" xmlns="http://www.w3.org/2000/svg"
                            fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                        </svg>
                    </a>

                    @if ($payrollStats['has_run'])
                        <div class="rounded-lg border border-slate-100 bg-white px-3 py-2.5">
                            <p class="text-[11px] font-medium text-slate-500 uppercase mb-1">
                                Latest payroll snapshot
                            </p>
                            <p class="text-xs text-slate-700">
                                Week {{ $payrollStats['week'] }}/{{ $payrollStats['year'] }} saved with
                                <span class="font-semibold">
                                    {{ number_format($payrollStats['total_gross'] ?? 0, 2) }}
                                </span>
                                total gross.
                            </p>
                        </div>
                    @else
                        <div class="rounded-lg border border-dashed border-slate-200 bg-slate-50 px-3 py-2.5">
                            <p class="text-xs text-slate-500">
                                Once you save your first weekly payroll, a quick summary will appear here.
                            </p>
                        </div>
                    @endif
                </div>
            </div>

        </div>
    </div>
@endsection
