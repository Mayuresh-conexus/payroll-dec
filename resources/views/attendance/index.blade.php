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
                                        <th class="px-4 py-3 text-left">Days (Mon–Sat, Sun off)</th>
                                        <th class="px-4 py-3 text-center">Present</th>
                                        <th class="px-4 py-3 text-center">Absent</th>
                                    </tr>
                                </thead>

                                <tbody class="divide-y divide-slate-100">
                                    @forelse($dailyEmployees as $employee)
                                        @php
                                            $att = $dailyAttendances[$employee->id] ?? null;
                                            $total = 6;

                                            $defaultMap = [
                                                'mon' => 1,
                                                'tue' => 1,
                                                'wed' => 1,
                                                'thu' => 1,
                                                'fri' => 1,
                                                'sat' => 1,
                                            ];

                                            if ($att && is_array($att->days_map)) {
                                                $initialDays = array_merge($defaultMap, $att->days_map);
                                            } else {
                                                $initialDays = $defaultMap;
                                            }

                                            $present = $att->present_days ?? array_sum($initialDays);

                                        @endphp

                                        <tr class="hover:bg-slate-50/80" x-data="{
                                            totalWork: {{ $total }},
                                            days: {
                                                mon: {{ $initialDays['mon'] }},
                                                tue: {{ $initialDays['tue'] }},
                                                wed: {{ $initialDays['wed'] }},
                                                thu: {{ $initialDays['thu'] }},
                                                fri: {{ $initialDays['fri'] }},
                                                sat: {{ $initialDays['sat'] }},
                                            },
                                            get presentCount() {
                                                return this.days.mon + this.days.tue + this.days.wed + this.days.thu + this.days.fri + this.days.sat;
                                            },
                                            get absentCount() {
                                                return this.totalWork - this.presentCount;
                                            }
                                        }">
                                            <td class="px-4 py-3 font-mono text-xs text-slate-600">
                                                {{ $employee->employee_code }}
                                            </td>
                                            <td class="px-4 py-3 text-sm font-medium text-slate-800">
                                                {{ $employee->name }}
                                            </td>
                                            <td class="px-4 py-3 text-sm text-slate-600">
                                                {{ $employee->department ?? 'No dept' }}
                                            </td>

                                            {{-- Days grid --}}
                                            <td class="px-4 py-3">
                                                <div class="flex flex-wrap items-center gap-1.5 text-xs">
                                                    {{-- Mon --}}
                                                    <button type="button" @click="days.mon = days.mon ? 0 : 1"
                                                        class="w-8 h-8 rounded-full border flex items-center justify-center font-semibold"
                                                        :class="days.mon ?
                                                            'bg-emerald-50 text-emerald-700 border-emerald-200' :
                                                            'bg-rose-50 text-rose-700 border-rose-200'">
                                                        Mo
                                                    </button>
                                                    {{-- Tue --}}
                                                    <button type="button" @click="days.tue = days.tue ? 0 : 1"
                                                        class="w-8 h-8 rounded-full border flex items-center justify-center font-semibold"
                                                        :class="days.tue ?
                                                            'bg-emerald-50 text-emerald-700 border-emerald-200' :
                                                            'bg-rose-50 text-rose-700 border-rose-200'">
                                                        Tu
                                                    </button>
                                                    {{-- Wed --}}
                                                    <button type="button" @click="days.wed = days.wed ? 0 : 1"
                                                        class="w-8 h-8 rounded-full border flex items-center justify-center font-semibold"
                                                        :class="days.wed ?
                                                            'bg-emerald-50 text-emerald-700 border-emerald-200' :
                                                            'bg-rose-50 text-rose-700 border-rose-200'">
                                                        We
                                                    </button>
                                                    {{-- Thu --}}
                                                    <button type="button" @click="days.thu = days.thu ? 0 : 1"
                                                        class="w-8 h-8 rounded-full border flex items-center justify-center font-semibold"
                                                        :class="days.thu ?
                                                            'bg-emerald-50 text-emerald-700 border-emerald-200' :
                                                            'bg-rose-50 text-rose-700 border-rose-200'">
                                                        Th
                                                    </button>
                                                    {{-- Fri --}}
                                                    <button type="button" @click="days.fri = days.fri ? 0 : 1"
                                                        class="w-8 h-8 rounded-full border flex items-center justify-center font-semibold"
                                                        :class="days.fri ?
                                                            'bg-emerald-50 text-emerald-700 border-emerald-200' :
                                                            'bg-rose-50 text-rose-700 border-rose-200'">
                                                        Fr
                                                    </button>
                                                    {{-- Sat --}}
                                                    <button type="button" @click="days.sat = days.sat ? 0 : 1"
                                                        class="w-8 h-8 rounded-full border flex items-center justify-center font-semibold"
                                                        :class="days.sat ?
                                                            'bg-emerald-50 text-emerald-700 border-emerald-200' :
                                                            'bg-rose-50 text-rose-700 border-rose-200'">
                                                        Sa
                                                    </button>
                                                    {{-- Sun fixed off --}}
                                                    <span
                                                        class="w-8 h-8 rounded-full border border-slate-200 bg-slate-100 text-slate-400 flex items-center justify-center font-semibold">
                                                        Su
                                                    </span>
                                                </div>

                                                {{-- Hidden inputs to submit --}}
                                                <input type="hidden" name="attendance[{{ $employee->id }}][days][mon]"
                                                    x-bind:value="days.mon">
                                                <input type="hidden" name="attendance[{{ $employee->id }}][days][tue]"
                                                    x-bind:value="days.tue">
                                                <input type="hidden" name="attendance[{{ $employee->id }}][days][wed]"
                                                    x-bind:value="days.wed">
                                                <input type="hidden" name="attendance[{{ $employee->id }}][days][thu]"
                                                    x-bind:value="days.thu">
                                                <input type="hidden" name="attendance[{{ $employee->id }}][days][fri]"
                                                    x-bind:value="days.fri">
                                                <input type="hidden" name="attendance[{{ $employee->id }}][days][sat]"
                                                    x-bind:value="days.sat">
                                            </td>

                                            {{-- Summary: present / absent --}}
                                            <td class="px-4 py-3 text-center text-sm">
                                                <span
                                                    class="inline-flex items-center justify-center px-2 py-1 rounded-full bg-emerald-50 text-emerald-700 text-xs">
                                                    <span x-text="presentCount"></span>
                                                    <span class="ml-1">present</span>
                                                </span>
                                            </td>
                                            <td class="px-4 py-3 text-center text-sm">
                                                <span
                                                    class="inline-flex items-center justify-center px-2 py-1 rounded-full bg-amber-50 text-amber-700 text-xs">
                                                    <span x-text="absentCount"></span>
                                                    <span class="ml-1">absent</span>
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
