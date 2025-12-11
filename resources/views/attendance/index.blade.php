@extends('layouts.app')

@section('title', 'Attendance')
@section('page_title', 'Weekly attendance')

@section('content')
    <div x-data="{
        tab: '{{ $tab }}',
        dailyLocked: {{ $dailyWeekLocked ? 'true' : 'false' }},
        hourlyLocked: {{ $hourlyWeekLocked ? 'true' : 'false' }}
    }" class="space-y-6">

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
                    <input type="hidden" name="lock_week" x-bind:value="dailyLocked ? 1 : 0">

                    <div class="p-4 space-y-4">

                        {{-- Week lock bar --}}
                        <div class="flex items-center justify-between text-xs">
                            <div class="flex items-center gap-3">
                                <div class="flex items-center gap-2">
                                    {{-- Modern toggle --}}
                                    <button type="button" @click="dailyLocked = !dailyLocked"
                                        class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors duration-200"
                                        :class="dailyLocked ? 'bg-emerald-500' : 'bg-slate-300'">
                                        <span
                                            class="inline-block h-4 w-4 transform rounded-full bg-white shadow-sm transition-transform duration-200"
                                            :class="dailyLocked ? 'translate-x-5' : 'translate-x-1'">
                                        </span>
                                    </button>

                                    <span class="text-slate-600"
                                        x-text="dailyLocked ? 'Week locked' : 'Week editable'"></span>
                                </div>

                                <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-[11px] font-medium"
                                    :class="dailyLocked
                                        ?
                                        'bg-emerald-50 text-emerald-700 border border-emerald-100' :
                                        'bg-amber-50 text-amber-700 border border-amber-100'">
                                    <span class="w-1.5 h-1.5 rounded-full"
                                        :class="dailyLocked ? 'bg-emerald-500' : 'bg-amber-400'"></span>
                                    <span
                                        x-text="dailyLocked ? 'Attendance frozen for this week' : 'You can edit attendance for this week'"></span>
                                </span>
                            </div>

                            <span class="text-slate-500">
                                Week {{ $week }} - {{ $year }}
                            </span>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="min-w-full text-sm">
                                <thead class="bg-slate-50 text-slate-500 uppercase text-xs font-semibold">
                                    <tr>
                                        <th class="px-4 py-3 text-left">Code</th>
                                        <th class="px-4 py-3 text-left">Name</th>
                                        <th class="px-4 py-3 text-left">Department</th>
                                        <th class="px-4 py-3 text-left">Days (Mon Sat, Sun off)</th>
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
                                        @endphp

                                        <tr class="hover:bg-slate-50/80 transition" x-data="{
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
                                                return this.days.mon + this.days.tue + this.days.wed +
                                                    this.days.thu + this.days.fri + this.days.sat;
                                            },
                                            get absentCount() {
                                                return this.totalWork - this.presentCount;
                                            }
                                        }"
                                            :class="dailyLocked ? 'opacity-60' : ''">
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
                                                    <button type="button" :disabled="dailyLocked"
                                                        @click="if (!dailyLocked) days.mon = days.mon ? 0 : 1"
                                                        class="w-8 h-8 rounded-full border flex items-center justify-center font-semibold transition"
                                                        :class="dailyLocked
                                                            ?
                                                            'bg-slate-100 text-slate-400 border-slate-200 cursor-not-allowed' :
                                                            (days.mon ?
                                                                'bg-emerald-50 text-emerald-700 border-emerald-200' :
                                                                'bg-rose-50 text-rose-700 border-rose-200')">
                                                        Mo
                                                    </button>

                                                    {{-- Tue --}}
                                                    <button type="button" :disabled="dailyLocked"
                                                        @click="if (!dailyLocked) days.tue = days.tue ? 0 : 1"
                                                        class="w-8 h-8 rounded-full border flex items-center justify-center font-semibold transition"
                                                        :class="dailyLocked
                                                            ?
                                                            'bg-slate-100 text-slate-400 border-slate-200 cursor-not-allowed' :
                                                            (days.tue ?
                                                                'bg-emerald-50 text-emerald-700 border-emerald-200' :
                                                                'bg-rose-50 text-rose-700 border-rose-200')">
                                                        Tu
                                                    </button>

                                                    {{-- Wed --}}
                                                    <button type="button" :disabled="dailyLocked"
                                                        @click="if (!dailyLocked) days.wed = days.wed ? 0 : 1"
                                                        class="w-8 h-8 rounded-full border flex items-center justify-center font-semibold transition"
                                                        :class="dailyLocked
                                                            ?
                                                            'bg-slate-100 text-slate-400 border-slate-200 cursor-not-allowed' :
                                                            (days.wed ?
                                                                'bg-emerald-50 text-emerald-700 border-emerald-200' :
                                                                'bg-rose-50 text-rose-700 border-rose-200')">
                                                        We
                                                    </button>

                                                    {{-- Thu --}}
                                                    <button type="button" :disabled="dailyLocked"
                                                        @click="if (!dailyLocked) days.thu = days.thu ? 0 : 1"
                                                        class="w-8 h-8 rounded-full border flex items-center justify-center font-semibold transition"
                                                        :class="dailyLocked
                                                            ?
                                                            'bg-slate-100 text-slate-400 border-slate-200 cursor-not-allowed' :
                                                            (days.thu ?
                                                                'bg-emerald-50 text-emerald-700 border-emerald-200' :
                                                                'bg-rose-50 text-rose-700 border-rose-200')">
                                                        Th
                                                    </button>

                                                    {{-- Fri --}}
                                                    <button type="button" :disabled="dailyLocked"
                                                        @click="if (!dailyLocked) days.fri = days.fri ? 0 : 1"
                                                        class="w-8 h-8 rounded-full border flex items-center justify-center font-semibold transition"
                                                        :class="dailyLocked
                                                            ?
                                                            'bg-slate-100 text-slate-400 border-slate-200 cursor-not-allowed' :
                                                            (days.fri ?
                                                                'bg-emerald-50 text-emerald-700 border-emerald-200' :
                                                                'bg-rose-50 text-rose-700 border-rose-200')">
                                                        Fr
                                                    </button>

                                                    {{-- Sat --}}
                                                    <button type="button" :disabled="dailyLocked"
                                                        @click="if (!dailyLocked) days.sat = days.sat ? 0 : 1"
                                                        class="w-8 h-8 rounded-full border flex items-center justify-center font-semibold transition"
                                                        :class="dailyLocked
                                                            ?
                                                            'bg-slate-100 text-slate-400 border-slate-200 cursor-not-allowed' :
                                                            (days.sat ?
                                                                'bg-emerald-50 text-emerald-700 border-emerald-200' :
                                                                'bg-rose-50 text-rose-700 border-rose-200')">
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
                            <button type="submit" class="px-4 py-2 rounded-lg text-sm font-medium transition"
                                :class="dailyLocked
                                    ?
                                    'bg-emerald-600 text-white hover:bg-emerald-700' :
                                    'bg-slate-900 text-white hover:bg-slate-800'">
                                <span x-text="dailyLocked ? 'Save and lock week' : 'Save daily rate attendance'"></span>
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
                    <input type="hidden" name="lock_week" x-bind:value="hourlyLocked ? 1 : 0">

                    <div class="p-4 space-y-4">

                        {{-- Week lock bar --}}
                        <div class="flex items-center justify-between text-xs">
                            <div class="flex items-center gap-3">
                                <div class="flex items-center gap-2">
                                    {{-- Modern toggle --}}
                                    <button type="button" @click="hourlyLocked = !hourlyLocked"
                                        class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors duration-200"
                                        :class="hourlyLocked ? 'bg-emerald-500' : 'bg-slate-300'">
                                        <span
                                            class="inline-block h-4 w-4 transform rounded-full bg-white shadow-sm transition-transform duration-200"
                                            :class="hourlyLocked ? 'translate-x-5' : 'translate-x-1'">
                                        </span>
                                    </button>

                                    <span class="text-slate-600"
                                        x-text="hourlyLocked ? 'Week locked' : 'Week editable'"></span>
                                </div>

                                <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-[11px] font-medium"
                                    :class="hourlyLocked
                                        ?
                                        'bg-emerald-50 text-emerald-700 border border-emerald-100' :
                                        'bg-amber-50 text-amber-700 border border-amber-100'">
                                    <span class="w-1.5 h-1.5 rounded-full"
                                        :class="hourlyLocked ? 'bg-emerald-500' : 'bg-amber-400'"></span>
                                    <span
                                        x-text="hourlyLocked ? 'Hours frozen for this week' : 'You can edit hours for this week'"></span>
                                </span>
                            </div>

                            <span class="text-slate-500">
                                Week {{ $week }} - {{ $year }}
                            </span>
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
                                        <tr class="hover:bg-slate-50/80 transition"
                                            :class="hourlyLocked ? 'opacity-60' : ''">
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
                                                    value="{{ $att->total_hours ?? '' }}" :readonly="hourlyLocked"
                                                    class="w-28 rounded-md border border-slate-200 px-2 py-1.5 text-sm text-center focus:ring-slate-500 focus:border-slate-500"
                                                    :class="hourlyLocked ? 'bg-slate-100 text-slate-400 cursor-not-allowed' : ''"
                                                    placeholder="0.00">
                                            </td>
                                            <td class="px-4 py-3 text-center">
                                                <input type="number" step="0.25" min="0"
                                                    name="attendance[{{ $employee->id }}][ot]"
                                                    value="{{ $att->overtime_hours ?? '' }}" :readonly="hourlyLocked"
                                                    class="w-28 rounded-md border border-slate-200 px-2 py-1.5 text-sm text-center focus:ring-slate-500 focus:border-slate-500"
                                                    :class="hourlyLocked ? 'bg-slate-100 text-slate-400 cursor-not-allowed' : ''"
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
                            <button type="submit" class="px-4 py-2 rounded-lg text-sm font-medium transition"
                                :class="hourlyLocked
                                    ?
                                    'bg-emerald-600 text-white hover:bg-emerald-700' :
                                    'bg-slate-900 text-white hover:bg-slate-800'">
                                <span x-text="hourlyLocked ? 'Save and lock week' : 'Save hourly attendance'"></span>
                            </button>
                        </div>

                    </div>
                </form>
            </div>
        </div>

    </div>
@endsection
