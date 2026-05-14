{{-- attendance/partials/_table.blade.php — Combined attendance grid with day cells, P/A counts --}}
<form action="{{ route('attendance.save') }}" method="post">
    @csrf
    <input type="hidden" name="year"      value="{{ $year }}">
    <input type="hidden" name="week"      value="{{ $week }}">
    <input type="hidden" name="lock_week" x-bind:value="lockWeek ? 1 : 0">

    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-4">

        {{-- Lock / copy bar --}}
        <div class="flex items-center gap-6 text-xs mb-4">
            <span class="text-white bg-gradient-to-r from-blue-500 via-blue-600 to-blue-700 font-medium rounded-full text-xs px-6 py-1 text-center leading-5">
                Week {{ $week }} - {{ $year }}
            </span>

            <div class="flex items-center gap-3">
                <button type="button" @click="lockWeek = !lockWeek"
                    class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors duration-200"
                    :class="lockWeek ? 'bg-emerald-500' : 'bg-slate-300'">
                    <span class="inline-block h-4 w-4 transform rounded-full bg-white shadow-sm transition-transform duration-200"
                        :class="lockWeek ? 'translate-x-5' : 'translate-x-1'"></span>
                </button>
                <span class="text-slate-600" x-text="lockWeek ? 'Week locked' : 'Week editable'"></span>
                <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-[11px] font-medium"
                    :class="lockWeek ? 'bg-emerald-50 text-emerald-700 border border-emerald-100' : 'bg-amber-50 text-amber-700 border border-amber-100'">
                    <span class="w-1.5 h-1.5 rounded-full" :class="lockWeek ? 'bg-emerald-500' : 'bg-amber-400'"></span>
                    <span x-text="lockWeek ? 'Attendance frozen for this week' : 'You can edit attendance for this week'"></span>
                </span>
            </div>

            <button type="button" @click="copyFromLastWeekToggle()" :disabled="lockWeek"
                :class="copied ? 'bg-green-700 hover:bg-green-900' : 'bg-slate-900 hover:bg-slate-700'"
                class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-white text-xs font-medium hover:shadow active:scale-[0.98] disabled:bg-slate-300 disabled:text-slate-500 disabled:cursor-not-allowed disabled:shadow-none">
                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                    <path stroke="white" stroke-linejoin="round" stroke-width="2" d="M14 4v3a1 1 0 0 1-1 1h-3m4 10v1a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V9a1 1 0 0 1 1-1h2m11-3v10a1 1 0 0 1-1 1h-7a1 1 0 0 1-1-1V7.87a1 1 0 0 1 .24-.65l2.46-2.87a1 1 0 0 1 .76-.35H18a1 1 0 0 1 1 1Z" />
                </svg>
                <span x-text="copied ? 'Revert copied data' : 'Copy from last week'"></span>
            </button>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                @php
                    use Carbon\Carbon;
                    $today  = Carbon::now();
                    $tyear  = $today->format('Y');
                    $tmonth = $today->format('m');
                    $tdate  = $today->format('d');
                @endphp

                <thead class="bg-white sticky top-0 z-10">
                    {{-- Row 1: column titles + date numbers --}}
                    <tr class="text-[11px] font-semibold uppercase tracking-wide text-slate-600 border-b border-slate-200">
                        <th class="px-3 py-3 text-left bg-white">Code</th>
                        <th class="px-3 py-3 text-left bg-white">Name</th>
                        <th class="px-3 py-3 text-left bg-white">Dept</th>
                        <th class="px-3 py-3 text-left bg-white">Type</th>
                        @foreach (['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'] as $d)
                            @php $isToday = isset($todayKey) && $todayKey === $d; @endphp
                            <th class="w-12 px-2 py-2 text-center bg-white border-l border-slate-100">
                                <div class="mx-auto w-10 rounded-lg px-1.5 py-1 {{ $isToday ? 'bg-[#2563EB] text-white shadow-sm' : 'bg-[#F6F7F9] text-slate-700' }}">
                                    <div class="text-[12px] leading-none font-semibold">{{ $dayDates[$d] ?? '' }}</div>
                                </div>
                            </th>
                        @endforeach
                        <th class="w-12 px-2 py-3 text-center bg-white border-l border-slate-100">P</th>
                        <th class="w-12 px-2 py-3 text-center bg-white border-l border-slate-100">A</th>
                    </tr>

                    {{-- Row 2: day labels --}}
                    <tr class="text-[10px] font-medium uppercase tracking-wider text-slate-500 border-b border-slate-200">
                        <th colspan="4" class="bg-white"></th>
                        @foreach (['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'] as $d)
                            @php $isToday = isset($todayKey) && $todayKey === $d; @endphp
                            <th class="w-12 px-2 py-2 text-center bg-white border-l border-slate-100">
                                <span class="inline-flex items-center justify-center rounded-md px-2 py-1 {{ $isToday ? 'bg-[#DBEAFE] text-[#1E40AF] font-semibold' : 'bg-transparent' }}">
                                    {{ strtoupper($d) }}
                                </span>
                            </th>
                        @endforeach
                        <th colspan="2" class="bg-white border-l border-slate-100"></th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-slate-100">
                    @php $allEmployees = $dailyEmployees->merge($hourlyEmployees); @endphp

                    @forelse($allEmployees as $employee)
                        @php
                            $dAtt    = $dailyAttendances[$employee->id]  ?? null;
                            $hAtt    = $hourlyAttendances[$employee->id] ?? null;
                            $daysMap = $dAtt ? $dAtt->days_map     ?? [] : [];
                            $dOtMap  = $dAtt ? $dAtt->overtime_map ?? [] : [];
                            $hHours  = $hAtt ? $hAtt->hours_map    ?? [] : [];
                            $hOt     = $hAtt ? $hAtt->ot_map       ?? [] : [];
                            $defaultHours       = $employee->hours_per_day ?? 0;
                            $defaultWorkingDays = ($employee->weekly_active_days ?? 0) > 0
                                ? (int) $employee->weekly_active_days
                                : 6;
                        @endphp

                        <tr data-employee="{{ $employee->id }}"
                            data-type="{{ $employee->type === 'daily_rate' ? 'daily' : 'hourly' }}"
                            class="hover:bg-slate-50/80 transition">

                            <td class="px-3 py-2 font-mono text-xs text-slate-600">{{ $employee->employee_code }}</td>
                            <td class="px-3 py-2 text-sm font-medium text-slate-800">{{ $employee->name }}</td>
                            <td class="px-3 py-2 text-sm text-slate-600">{{ $employee->department ?? 'Not set' }}</td>
                            <td class="px-3 py-2 text-xs text-slate-600">{{ $employee->type === 'daily_rate' ? 'Daily' : 'Hourly' }}</td>

                            @foreach (['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'] as $d)
                                <td class="px-1 py-1 align-top text-center">
                                    @php
                                        $isWeekendOff = $defaultWorkingDays == 6
                                            ? $d === 'sun'
                                            : in_array($d, ['sat', 'sun']);
                                        $dayKey   = "attendance.{$employee->id}.days.{$d}";
                                        $hoursKey = "attendance.{$employee->id}.hours_map.{$d}";
                                        $defaultHourValue = $employee->type === 'hourly'
                                            ? ($isWeekendOff ? 0 : $defaultHours)
                                            : 0;
                                        if ($employee->type === 'hourly') {
                                            $hVal    = old($hoursKey, $hHours[$d] ?? $defaultHourValue);
                                            $checked = (int) old($dayKey, (float) $hVal > 0 ? 1 : 0);
                                        } else {
                                            $checked = (int) old($dayKey, $daysMap[$d] ?? ($isWeekendOff ? 0 : 1));
                                        }
                                        $oVal = old("attendance.{$employee->id}.overtime_map.{$d}", $dOtMap[$d] ?? 0);
                                    @endphp

                                    <div data-day="{{ $d }}" x-data="{
                                        present: {{ $checked ? 'true' : 'false' }},
                                        inputName: 'attendance[{{ $employee->id }}][days][{{ $d }}]',
                                        hoursName: 'attendance[{{ $employee->id }}][hours_map][{{ $d }}]',
                                        hoursVal: {{ $checked ? $hHours[$d] ?? $defaultHourValue : 0 }}
                                    }" class="flex flex-col items-center gap-1">

                                        <button type="button"
                                            @click="present = !present; if (present) { hoursVal = hoursVal > 0 ? hoursVal : {{ $defaultHours }} } else { hoursVal = 0 }"
                                            :class="present ? 'bg-emerald-500 text-white' : 'bg-red-500 text-white'"
                                            class="w-7 h-7 rounded-full flex items-center justify-center text-[11px] font-semibold shadow-sm transition-colors duration-150">
                                            <span x-text="present ? 'P' : 'A'"></span>
                                        </button>

                                        <input type="hidden" :name="inputName" :value="present ? 1 : 0">

                                        <div class="mt-1 flex items-center gap-1 overflow-visible">
                                            @if ($employee->type === 'hourly')
                                                <input type="number" step="0.25" min="0"
                                                    :name="hoursName" x-model.number="hoursVal"
                                                    class="w-12 text-[11px] px-1 py-0.5 border border-slate-200 rounded text-center focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500/30 outline-none transition-all shadow-sm"
                                                    :disabled="!present">
                                            @else
                                                <input type="number" step="0.25" min="0"
                                                    name="attendance[{{ $employee->id }}][overtime_map][{{ $d }}]"
                                                    value="{{ $oVal }}"
                                                    class="w-12 text-[10px] px-1 py-0.5 border border-slate-200 rounded text-center focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500/30 outline-none transition-all shadow-sm"
                                                    placeholder="OT">
                                            @endif

                                            @if ($d === 'sun')
                                                <span class="inline-flex items-center gap-1 text-[10px] text-slate-500">
                                                    @if ($employee->type === 'hourly')
                                                        <span class="font-medium">Hrs</span>
                                                    @else
                                                        <span class="font-medium">(€)</span>
                                                    @endif
                                                    <span class="relative inline-flex items-center group">
                                                        <svg class="w-3.5 h-3.5 text-slate-400 group-hover:text-slate-600 cursor-help"
                                                            xmlns="http://www.w3.org/2000/svg" fill="none"
                                                            viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6">
                                                            <circle cx="12" cy="12" r="9" />
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8.25v.5M12 11.25v4.5" />
                                                        </svg>
                                                        <span class="pointer-events-none absolute right-full top-1/2 -translate-y-1/2 mr-2 hidden group-hover:block z-50 max-w-[220px] whitespace-normal break-words rounded-md bg-slate-900 px-2 py-1 w-max text-[10px] leading-snug text-white shadow-lg opacity-0 group-hover:opacity-100 transition-opacity duration-150 ease-out">
                                                            @if ($employee->type === 'hourly')
                                                                Enter total worked hours. Add extra hours beyond normal shift for overtime.
                                                            @else
                                                                Enter overtime amount directly in €.
                                                            @endif
                                                        </span>
                                                    </span>
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                            @endforeach

                            @php
                                $workDays = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];
                                if ($employee->type === 'daily_rate') {
                                    $presentCount = array_sum(array_map('intval', array_intersect_key($daysMap, array_flip($workDays))));
                                } else {
                                    $presentCount = count(array_filter(array_intersect_key($hHours, array_flip($workDays)), fn($h) => (float) $h > 0));
                                }
                                $absentCount = max(0, count($workDays) - $presentCount);
                            @endphp
                            <td class="px-2 py-2 text-center">
                                <span class="inline-flex items-center justify-center px-2 py-1 rounded-full text-[11px] font-medium bg-emerald-50 text-emerald-700 border border-emerald-100">{{ $presentCount }}</span>
                            </td>
                            <td class="px-2 py-2 text-center">
                                <span class="inline-flex items-center justify-center px-2 py-1 rounded-full text-[11px] font-medium bg-rose-50 text-rose-700 border border-rose-100">{{ $absentCount }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="11" class="px-4 py-6 text-center text-sm text-slate-500">No employees found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="pt-4 flex justify-end">
            <button type="submit" class="px-4 py-2 rounded-lg text-sm font-medium transition"
                :class="lockWeek ? 'bg-emerald-600 text-white hover:bg-emerald-700' : 'bg-slate-900 text-white hover:bg-slate-800'">
                <span x-text="lockWeek ? 'Save and lock week' : 'Save attendance'"></span>
            </button>
        </div>
    </div>
</form>

<script>
    window.prevAttendance = @json([
        'daily'  => $prevDailyAttendances,
        'hourly' => $prevHourlyAttendances,
    ]);
</script>
