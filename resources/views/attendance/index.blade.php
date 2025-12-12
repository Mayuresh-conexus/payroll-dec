@extends('layouts.app')

@section('title', 'Attendance')
@section('page_title', 'Weekly attendance')

@section('content')
    <div x-data="{
        year: {{ $year }},
        week: {{ $week }},
        lockWeek: {{ $dailyWeekLocked || $hourlyWeekLocked ? 'true' : 'false' }}
    }" class="space-y-6">

        {{-- Header / Filters same as before --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold text-slate-800">Weekly attendance</h1>
                <p class="text-sm text-slate-500">
                    Mark daily-rate presence and hourly hours for a selected week.
                </p>
            </div>
        </div>

        <form method="get" action="{{ route('attendance.index') }}"
            class="bg-white rounded-xl shadow-sm border border-slate-200 p-4 flex flex-wrap items-center gap-4 text-sm">
            <input type="hidden" name="tab" value="combined">
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

        {{-- Combined attendance form --}}
        <form action="{{ route('attendance.save') }}" method="post">
            @csrf
            <input type="hidden" name="year" value="{{ $year }}">
            <input type="hidden" name="week" value="{{ $week }}">
            <input type="hidden" name="lock_week" x-bind:value="lockWeek ? 1 : 0">

            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-4">

                {{-- Lock bar --}}
                <div class="flex items-center justify-between text-xs mb-4">
                    <div class="flex items-center gap-3">
                        <button type="button" @click="lockWeek = !lockWeek"
                            class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors duration-200"
                            :class="lockWeek ? 'bg-emerald-500' : 'bg-slate-300'">
                            <span
                                class="inline-block h-4 w-4 transform rounded-full bg-white shadow-sm transition-transform duration-200"
                                :class="lockWeek ? 'translate-x-5' : 'translate-x-1'">
                            </span>
                        </button>
                        <span class="text-slate-600" x-text="lockWeek ? 'Week locked' : 'Week editable'"></span>
                        <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-[11px] font-medium"
                            :class="lockWeek ? 'bg-emerald-50 text-emerald-700 border border-emerald-100' :
                                'bg-amber-50 text-amber-700 border border-amber-100'">
                            <span class="w-1.5 h-1.5 rounded-full"
                                :class="lockWeek ? 'bg-emerald-500' : 'bg-amber-400'"></span>
                            <span
                                x-text="lockWeek ? 'Attendance frozen for this week' : 'You can edit attendance for this week'"></span>
                        </span>
                    </div>
                    <span class="text-slate-500">Week {{ $week }} - {{ $year }}</span>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50 text-slate-500 uppercase text-xs font-semibold">
                            <tr>
                                <th class="px-3 py-3 text-left">Code</th>
                                <th class="px-3 py-3 text-left">Name</th>
                                <th class="px-3 py-3 text-left">Dept</th>
                                <th class="px-3 py-3 text-left">Type</th>

                                {{-- Day headers (Mon..Sun) --}}
                                @foreach (['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'] as $d)
                                    <th class="w-10 px-1 py-2 text-center text-[10px]">{{ strtoupper(substr($d, 0, 3)) }}
                                    </th>
                                @endforeach
                                <th class="w-12 px-1 py-2 text-center text-[10px]">P</th>
                                <th class="w-12 px-1 py-2 text-center text-[10px]">A</th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-slate-100">
                            @php
                                $allEmployees = $dailyEmployees->merge($hourlyEmployees);
                            @endphp

                            @forelse($allEmployees as $employee)
                                @php
                                    $dAtt = $dailyAttendances[$employee->id] ?? null;
                                    $hAtt = $hourlyAttendances[$employee->id] ?? null;
                                    $daysMap = $dAtt ? $dAtt->days_map ?? [] : [];
                                    $dOtMap = $dAtt ? $dAtt->overtime_map ?? [] : [];
                                    $hHours = $hAtt ? $hAtt->hours_map ?? [] : [];
                                    $hOt = $hAtt ? $hAtt->ot_map ?? [] : [];
                                    $defaultHours = $employee->hours_per_day ?? 0;
                                @endphp

                                <tr class="hover:bg-slate-50/80 transition">
                                    <td class="px-3 py-2 font-mono text-xs text-slate-600">{{ $employee->employee_code }}
                                    </td>
                                    <td class="px-3 py-2 text-sm font-medium text-slate-800">{{ $employee->name }}</td>
                                    <td class="px-3 py-2 text-sm text-slate-600">{{ $employee->department ?? 'Not set' }}
                                    </td>
                                    <td class="px-3 py-2 text-xs text-slate-600">
                                        {{ $employee->type === 'daily_rate' ? 'Daily' : 'Hourly' }}</td>

                                    {{-- per-day cells --}}
                                    @foreach (['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'] as $d)
                                        <td class="px-1 py-1 align-top text-center">
                                            @php
                                                // determine present/absent. For hourly, prefer hours>0 when days flag not provided.
                                                $dayKey = "attendance.{$employee->id}.days.{$d}";
                                                $hoursKey = "attendance.{$employee->id}.hours_map.{$d}";

                                                if ($employee->type === 'hourly') {
                                                    // prefer saved hours when present; otherwise default: sunday=0, others = employee default
                                                    $hDefault = isset($hHours[$d])
                                                        ? $hHours[$d]
                                                        : ($d === 'sun'
                                                            ? 0
                                                            : $defaultHours);
                                                    $hVal = old($hoursKey, $hDefault);
                                                    $derived = (float) $hVal > 0 ? 1 : 0;
                                                    $checked = (int) old($dayKey, $derived);
                                                } else {
                                                    $checked = (int) old(
                                                        $dayKey,
                                                        $daysMap[$d] ?? ($d === 'sun' ? 0 : 1),
                                                    );
                                                }

                                                $oVal = old(
                                                    "attendance.{$employee->id}.overtime_map.{$d}",
                                                    $dOtMap[$d] ?? 0,
                                                );
                                            @endphp

                                            <div x-data="{
                                                present: {{ $checked ? 'true' : 'false' }},
                                                inputName: 'attendance[{{ $employee->id }}][days][{{ $d }}]',
                                                hoursName: 'attendance[{{ $employee->id }}][hours_map][{{ $d }}]',
                                                hoursVal: @if ($employee->type === 'hourly') @json(old(
                                                        "attendance.{$employee->id}.hours_map.{$d}",
                                                        isset($hHours[$d]) ? $hHours[$d] : ($d === 'sun' ? 0 : $defaultHours))) @else 0 @endif
                                            }" class="flex flex-col items-center gap-1">
                                                <!-- Tiny Present/Absent toggle -->
                                                <button type="button"
                                                    @click="present = !present; if (present) { hoursVal = hoursVal > 0 ? hoursVal : {{ $defaultHours }} } else { hoursVal = 0 }"
                                                    :class="present ? 'bg-emerald-500 text-white' : 'bg-red-500 text-white'"
                                                    class="w-7 h-7 rounded-full flex items-center justify-center text-[11px] font-semibold shadow-sm transition-colors duration-150"
                                                    :aria-pressed="present ? 'true' : 'false'"
                                                    :title="present ? 'Mark absent' : 'Mark present'">
                                                    <span x-text="present ? 'P' : 'A'"></span>
                                                </button>

                                                <!-- Hidden input submits 1 or 0 -->
                                                <input type="hidden" :name="inputName" :value="present ? 1 : 0">

                                                <!-- For hourly employees show hours input bound to Alpine; for daily-rate keep OT input -->
                                                @if ($employee->type === 'hourly')
                                                    <input type="number" step="0.25" min="0"
                                                        :name="hoursName" x-model.number="hoursVal"
                                                        class="mt-1 w-14 text-[11px] px-1 py-0.5 border rounded"
                                                        title="Hours" :disabled="!present">
                                                @else
                                                    <input type="number" step="0.25" min="0"
                                                        name="attendance[{{ $employee->id }}][overtime_map][{{ $d }}]"
                                                        value="{{ $oVal }}"
                                                        class="mt-1 w-12 text-[10px] px-1 py-0.5 border rounded"
                                                        placeholder="OT" title="Overtime hours">
                                                @endif
                                            </div>
                                        </td>
                                    @endforeach


                                    @php
                                        // days keys we consider for presence counts (include sunday when marked)
                                        $workDays = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];

                                        if ($employee->type === 'daily_rate') {
                                            $daysMapForCount = $daysMap ?? [];
                                            $presentCount = 0;
                                            foreach ($workDays as $d) {
                                                $presentCount += isset($daysMapForCount[$d])
                                                    ? (int) $daysMapForCount[$d]
                                                    : 0;
                                            }
                                        } else {
                                            // hourly: consider a day present when hours > 0
                                            $hoursMapForCount = $hHours ?? [];
                                            $presentCount = 0;
                                            foreach ($workDays as $d) {
                                                $h = isset($hoursMapForCount[$d]) ? (float) $hoursMapForCount[$d] : 0;
                                                if ($h > 0) {
                                                    $presentCount++;
                                                }
                                            }
                                        }

                                        $absentCount = max(0, count($workDays) - $presentCount);
                                    @endphp

                                    <td class="px-2 py-2 text-center">
                                        <span
                                            class="inline-flex items-center justify-center px-2 py-1 rounded-full text-[11px] font-medium bg-emerald-50 text-emerald-700 border border-emerald-100">
                                            {{ $presentCount }}
                                        </span>
                                    </td>

                                    <td class="px-2 py-2 text-center">
                                        <span
                                            class="inline-flex items-center justify-center px-2 py-1 rounded-full text-[11px] font-medium bg-rose-50 text-rose-700 border border-rose-100">
                                            {{ $absentCount }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="11" class="px-4 py-6 text-center text-sm text-slate-500">
                                        No employees found.
                                    </td>
                                </tr>
                            @endforelse

                        </tbody>
                    </table>
                </div>

                <div class="pt-4 flex justify-end">
                    <button type="submit" class="px-4 py-2 rounded-lg text-sm font-medium transition"
                        :class="lockWeek ? 'bg-emerald-600 text-white hover:bg-emerald-700' :
                            'bg-slate-900 text-white hover:bg-slate-800'">
                        <span x-text="lockWeek ? 'Save and lock week' : 'Save attendance'"></span>
                    </button>
                </div>
            </div>
        </form>
    </div>
@endsection
