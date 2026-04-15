@extends('layouts.app')

@section('title', 'Attendance')
@section('page_title', 'Weekly attendance')
@section('page_header', 'Weekly Attendance')
@section('page_subtitle', 'Log and review daily presence and hourly records for the selected week.')

@section('page_action')
    <form method="get" action="{{ route('attendance.index') }}" class="flex flex-wrap items-center gap-3">
        <input type="hidden" name="tab" value="combined">
        
        <label class="sr-only">Year</label>
        <select name="year" class="rounded-lg border-slate-300 shadow-sm text-sm py-2 pl-3 pr-8 focus:ring-slate-500 focus:border-slate-500">
            @for ($y = now()->year - 2; $y <= now()->year + 10; $y++)
                <option value="{{ $y }}" @selected($y == $year)>{{ $y }}</option>
            @endfor
        </select>

        <label class="sr-only">Week</label>
        <select name="week" class="rounded-lg border-slate-300 shadow-sm text-sm py-2 pl-3 pr-8 focus:ring-slate-500 focus:border-slate-500">
            @for ($w = 1; $w <= $weeksInYear; $w++)
                <option value="{{ $w }}" @selected($w == $week)>Week {{ $w }}</option>
            @endfor
        </select>

        <button type="submit" class="px-4 py-2 rounded-lg bg-slate-900 text-white text-sm font-medium hover:bg-slate-800 transition shadow-sm">
            Load
        </button>
    </form>
@endsection

@section('content')
    <div x-data="{
        year: {{ $year }},
        week: {{ $week }},
        lockWeek: {{ $dailyWeekLocked || $hourlyWeekLocked ? 'true' : 'false' }},
        copied: false,
        originalData: {},

        snapshotCurrent() {
            this.originalData = {};
            document.querySelectorAll('[data-employee]').forEach(row => {
                const employeeId = row.dataset.employee;
                const type = row.dataset.type;
                this.originalData[employeeId] = { type, days: {} };
                ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'].forEach(day => {
                    const cell = row.querySelector(`[data-day='${day}']`);
                    if (!cell) return;
                    const data = Alpine.$data(cell);
                    if (!data) return;
                    this.originalData[employeeId].days[day] = {
                        present: data.present,
                        hoursVal: type === 'hourly' ? data.hoursVal : null
                    };
                });
            });
        },

        restoreSnapshot() {
            Object.entries(this.originalData).forEach(([employeeId, emp]) => {
                const row = document.querySelector(`[data-employee='${employeeId}']`);
                if (!row) return;
                Object.entries(emp.days).forEach(([day, values]) => {
                    const cell = row.querySelector(`[data-day='${day}']`);
                    if (!cell) return;
                    const data = Alpine.$data(cell);
                    if (!data) return;
                    data.present = values.present;
                    if (emp.type === 'hourly') data.hoursVal = values.hoursVal ?? 0;
                });
            });
        },

        copyFromLastWeekToggle() {
            if (this.lockWeek) return;
            if (!this.copied) {
                this.snapshotCurrent();
                this.applyLastWeek();
                this.copied = true;
            } else {
                this.restoreSnapshot();
                this.copied = false;
            }
        },

        applyLastWeek() {
            document.querySelectorAll('[data-employee]').forEach(row => {
                const employeeId = row.dataset.employee;
                const type = row.dataset.type;
                const prev = type === 'daily'
                    ? window.prevAttendance.daily[employeeId]
                    : window.prevAttendance.hourly[employeeId];
                if (!prev) return;
                const days = type === 'daily' ? (prev.days_map ?? {}) : (prev.hours_map ?? {});
                ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'].forEach(day => {
                    const cell = row.querySelector(`[data-day='${day}']`);
                    if (!cell) return;
                    const data = Alpine.$data(cell);
                    if (!data) return;
                    if (type === 'daily') {
                        data.present = days[day] == 1;
                    } else {
                        const hrs = parseFloat(days[day] ?? 0);
                        data.present  = hrs > 0;
                        data.hoursVal = hrs > 0 ? hrs : 0;
                    }
                });
            });
        }
    }" class="space-y-6">

        {{-- Payroll-already-saved warning --}}
        @if (!empty($weekHasPayroll))
            <div class="rounded-lg bg-amber-50 border border-amber-200 px-4 py-3 text-sm text-amber-800 flex items-start gap-3">
                <svg class="w-5 h-5 flex-shrink-0 mt-0.5 text-amber-500" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.008v.008H12v-.008Z" />
                </svg>
                <div>
                    <strong>Payroll already generated for Week {{ $week }}, {{ $year }}.</strong>
                    Editing attendance now will update the payroll sheet automatically on next load, but only if attendance is <em>not locked</em>.
                    <a href="{{ route('payroll.index', ['year' => $year, 'week' => $week]) }}" class="underline font-medium ml-1">View payroll →</a>
                </div>
            </div>
        @endif



        @include('attendance.partials._table')

    </div>
@endsection
