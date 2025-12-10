@extends('layouts.app')

@section('title', 'Attendance')
@section('page_title', 'Weekly attendance')

@section('content')
    <div x-data="{ tab: '{{ $tab }}' }" class="space-y-6">

        {{-- Header --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold text-slate-800">Weekly attendance</h1>
                <p class="text-sm text-slate-500">
                    Mark daily rate presence and hourly hours for a selected week.
                </p>
            </div>
        </div>

        {{-- Filters: year + week selector --}}
        <form method="get" action="{{ route('attendance.index') }}"
            class="bg-white rounded-xl shadow-sm border border-slate-200 p-4 flex flex-wrap items-center gap-4 text-sm">
            <input type="hidden" name="tab" x-model="tab">

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
                <label class="block text-xs font-semibold text-slate-600 mb-1">Week number</label>
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
                    Load week
                </button>
            </div>
        </form>

        {{-- Tabs --}}
        <div class="bg-white rounded-xl shadow-sm border border-slate-200">
            <div class="border-b border-slate-200 flex text-sm">
                <button type="button" @click="tab = 'daily'" class="flex-1 px-4 py-2.5 text-center font-medium"
                    :class="tab === 'daily'
                        ?
                        'text-slate-900 border-b-2 border-slate-900' :
                        'text-slate-500 hover:text-slate-800'">
                    Daily rate staff
                </button>
                <button type="button" @click="tab = 'hourly'" class="flex-1 px-4 py-2.5 text-center font-medium"
                    :class="tab === 'hourly'
                        ?
                        'text-slate-900 border-b-2 border-slate-900' :
                        'text-slate-500 hover:text-slate-800'">
                    Hourly staff
                </button>
            </div>

            {{-- Daily rate tab --}}
            <div x-show="tab === 'daily'" x-cloak>
                <form action="{{ route('attendance.daily_rate.store') }}" method="post">
                    @csrf
                    <input type="hidden" name="year" value="{{ $year }}">
                    <input type="hidden" name="week" value="{{ $week }}">

                    <div class="p-4">
                        <div class="mb-3 flex items-center justify-between text-xs text-slate-500">
                            <span>All daily rate employees default to 6 working days. Adjust present days where
                                needed.</span>
                            <span>Week {{ $week }} - {{ $year }}</span>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="min-w-full text-sm">
                                <thead class="bg-slate-50 text-slate-500 uppercase text-xs font-semibold">
                                    <tr>
                                        <th class="px-4 py-3 text-left">Code</th>
                                        <th class="px-4 py-3 text-left">Name</th>
                                        <th class="px-4 py-3 text-left">Department</th>
                                        <th class="px-4 py-3 text-center">Total days</th>
                                        <th class="px-4 py-3 text-center">Present days</th>
                                        <th class="px-4 py-3 text-center">Absent</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    @forelse($dailyEmployees as $employee)
                                        @php
                                            $att = $dailyAttendances[$employee->id] ?? null;
                                            $present = $att->present_days ?? 6;
                                            $total = $att->total_working_days ?? 6;
                                        @endphp
                                        <tr class="hover:bg-slate-50/80" x-data="{ present: {{ $present }}, total: {{ $total }} }">
                                            <td class="px-4 py-3 font-mono text-xs text-slate-600">
                                                {{ $employee->employee_code }}
                                            </td>
                                            <td class="px-4 py-3 text-sm font-medium text-slate-800">
                                                {{ $employee->name }}
                                            </td>
                                            <td class="px-4 py-3 text-sm text-slate-600">
                                                {{ $employee->department ?? 'No dept' }}
                                            </td>
                                            <td class="px-4 py-3 text-center text-sm text-slate-700">
                                                <span
                                                    class="inline-flex items-center justify-center px-2 py-1 rounded-full bg-slate-100">
                                                    <span class="font-semibold">{{ $total }}</span>
                                                    <span class="ml-1 text-xs text-slate-500">days</span>
                                                </span>
                                            </td>
                                            <td class="px-4 py-3 text-center">
                                                <div class="inline-flex items-center gap-1">
                                                    <button type="button" @click="present = Math.max(0, present - 1)"
                                                        class="w-7 h-7 flex items-center justify-center rounded-md border border-slate-200 text-slate-600 hover:bg-slate-50">
                                                        -
                                                    </button>
                                                    <input type="number"
                                                        class="w-14 text-center rounded-md border border-slate-200 text-sm focus:ring-slate-500 focus:border-slate-500"
                                                        x-model="present" min="0" :max="total">
                                                    <button type="button" @click="present = Math.min(total, present + 1)"
                                                        class="w-7 h-7 flex items-center justify-center rounded-md border border-slate-200 text-slate-600 hover:bg-slate-50">
                                                        +
                                                    </button>
                                                </div>
                                                <input type="hidden" :value="present"
                                                    name="attendance[{{ $employee->id }}][present_days]">
                                            </td>
                                            <td class="px-4 py-3 text-center text-sm">
                                                <span
                                                    class="inline-flex items-center justify-center px-2 py-1 rounded-full bg-amber-50 text-amber-700 text-xs"
                                                    x-text="(total - present) + ' day(s)'">
                                                </span>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6" class="px-4 py-6 text-center text-sm text-slate-500">
                                                No daily rate employees found.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        <div class="pt-4 flex justify-end">
                            <button type="submit"
                                class="px-4 py-2 rounded-lg bg-slate-900 text-white text-sm font-medium hover:bg-slate-800">
                                Save daily rate attendance
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            {{-- Hourly tab --}}
            <div x-show="tab === 'hourly'" x-cloak>
                <form action="{{ route('attendance.hourly.store') }}" method="post">
                    @csrf
                    <input type="hidden" name="year" value="{{ $year }}">
                    <input type="hidden" name="week" value="{{ $week }}">

                    <div class="p-4">
                        <div class="mb-3 flex items-center justify-between text-xs text-slate-500">
                            <span>Enter total hours and overtime hours for this week.</span>
                            <span>Week {{ $week }} - {{ $year }}</span>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="min-w-full text-sm">
                                <thead class="bg-slate-50 text-slate-500 uppercase text-xs font-semibold">
                                    <tr>
                                        <th class="px-4 py-3 text-left">Code</th>
                                        <th class="px-4 py-3 text-left">Name</th>
                                        <th class="px-4 py-3 text-left">Department</th>
                                        <th class="px-4 py-3 text-center">Hours</th>
                                        <th class="px-4 py-3 text-center">OT hours</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    @forelse($hourlyEmployees as $employee)
                                        @php
                                            $att = $hourlyAttendances[$employee->id] ?? null;
                                        @endphp
                                        <tr class="hover:bg-slate-50/80">
                                            <td class="px-4 py-3 font-mono text-xs text-slate-600">
                                                {{ $employee->employee_code }}
                                            </td>
                                            <td class="px-4 py-3 text-sm font-medium text-slate-800">
                                                {{ $employee->name }}
                                            </td>
                                            <td class="px-4 py-3 text-sm text-slate-600">
                                                {{ $employee->department ?? 'No dept' }}
                                            </td>
                                            <td class="px-4 py-3 text-center">
                                                <input type="number" step="0.25" min="0"
                                                    name="attendance[{{ $employee->id }}][hours]"
                                                    value="{{ $att->total_hours ?? '' }}"
                                                    class="w-28 rounded-md border border-slate-200 px-2 py-1.5 text-sm text-center focus:ring-slate-500 focus:border-slate-500"
                                                    placeholder="0.00">
                                            </td>
                                            <td class="px-4 py-3 text-center">
                                                <input type="number" step="0.25" min="0"
                                                    name="attendance[{{ $employee->id }}][ot]"
                                                    value="{{ $att->overtime_hours ?? '' }}"
                                                    class="w-28 rounded-md border border-slate-200 px-2 py-1.5 text-sm text-center focus:ring-slate-500 focus:border-slate-500"
                                                    placeholder="0.00">
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="px-4 py-6 text-center text-sm text-slate-500">
                                                No hourly employees found.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        <div class="pt-4 flex justify-end">
                            <button type="submit"
                                class="px-4 py-2 rounded-lg bg-slate-900 text-white text-sm font-medium hover:bg-slate-800">
                                Save hourly attendance
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

    </div>
@endsection
