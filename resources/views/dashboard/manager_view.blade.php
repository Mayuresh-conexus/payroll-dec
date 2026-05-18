{{--
    Manager dashboard partial — included from dashboard.blade.php when isManagerView = true.
    Variables available: $teamCards, $dayKeys, $dayDates, $currentYear, $currentWeek
--}}

@php
    $dayLabels = ['mon' => 'M', 'tue' => 'T', 'wed' => 'W', 'thu' => 'T', 'fri' => 'F', 'sat' => 'S', 'sun' => 'S'];
@endphp

@if ($teamCards->isEmpty())
    <div class="bg-white rounded-xl border border-dashed border-slate-200 px-6 py-16 text-center">
        <svg class="mx-auto mb-3 w-10 h-10 text-slate-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z" />
        </svg>
        <p class="text-sm font-medium text-slate-500">No employees assigned to you yet.</p>
        <p class="text-xs text-slate-400 mt-1">Ask an admin to assign employees to your team.</p>
    </div>
@else
    {{-- Summary bar --}}
    <div class="flex items-center gap-4 mb-5">
        <h2 class="text-lg font-bold text-slate-800 tracking-tight flex-1">My Team</h2>
        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-100">
            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
            {{ $markedCount }} marked
        </span>
        @if ($unmarkedCount > 0)
            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-amber-50 text-amber-700 border border-amber-100">
                <span class="w-1.5 h-1.5 rounded-full bg-amber-400 animate-pulse"></span>
                {{ $unmarkedCount }} pending
            </span>
        @endif
    </div>

    {{-- Employee cards grid --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
        @foreach ($teamCards as $card)
            @php
                $emp      = $card['employee'];
                $isDaily  = $card['type'] === 'daily';
                $marked   = $card['marked'];
                $locked   = $card['locked'];
                $attendanceUrl = route('attendance.index', [
                    'year' => $currentYear,
                    'week' => $currentWeek,
                    'tab'  => $isDaily ? 'daily' : 'hourly',
                ]);
            @endphp

            <div class="bg-white rounded-xl border border-slate-200 shadow-sm flex flex-col hover:shadow-md transition duration-200
                {{ $locked ? 'border-l-4 border-l-slate-400' : ($marked ? 'border-l-4 border-l-emerald-400' : 'border-l-4 border-l-amber-400') }}">

                {{-- Card header --}}
                <div class="px-4 pt-4 pb-3 flex items-start justify-between gap-2">
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-bold text-slate-800 truncate">{{ $emp->name }}</p>
                        <p class="text-xs text-slate-500 mt-0.5">
                            {{ $emp->employee_code }}
                            @if ($emp->department)
                                <span class="text-slate-300 mx-1">·</span>{{ $emp->department }}
                            @endif
                        </p>
                    </div>
                    <div class="flex items-center gap-1.5 shrink-0">
                        @if ($locked)
                            <span class="inline-flex px-1.5 py-0.5 rounded text-[10px] font-bold bg-slate-100 text-slate-500 uppercase tracking-wide">Locked</span>
                        @elseif ($marked)
                            <span class="inline-flex px-1.5 py-0.5 rounded text-[10px] font-bold bg-emerald-50 text-emerald-700 uppercase tracking-wide">Marked</span>
                        @else
                            <span class="inline-flex px-1.5 py-0.5 rounded text-[10px] font-bold bg-amber-50 text-amber-700 uppercase tracking-wide">Pending</span>
                        @endif
                        <span class="inline-flex px-1.5 py-0.5 rounded text-[10px] font-bold uppercase tracking-wide
                            {{ $isDaily ? 'bg-violet-50 text-violet-700' : 'bg-brand-50 text-brand-700' }}">
                            {{ $isDaily ? 'Daily' : 'Hourly' }}
                        </span>
                    </div>
                </div>

                {{-- 7-day attendance dots --}}
                <div class="px-4 pb-3">
                    <div class="flex items-center gap-1">
                        @foreach ($dayKeys as $day)
                            @php
                                $label = $dayLabels[$day];
                                if ($isDaily) {
                                    $val    = $card['days_map'][$day] ?? null;
                                    $status = $val === null ? 'none' : ((int) $val === 1 ? 'present' : 'absent');
                                } else {
                                    $val    = $card['hours_map'][$day] ?? null;
                                    $status = $val === null ? 'none' : ((float) $val > 0 ? 'present' : 'absent');
                                    $hrs    = $val !== null ? rtrim(rtrim(number_format((float) $val, 1), '0'), '.') : null;
                                }
                            @endphp
                            <div class="flex-1 flex flex-col items-center gap-0.5">
                                <span class="text-[9px] font-semibold uppercase tracking-wide
                                    {{ $status === 'present' ? 'text-emerald-600' : ($status === 'absent' ? 'text-rose-400' : 'text-slate-300') }}">
                                    {{ $label }}
                                </span>
                                @if ($isDaily)
                                    <div class="w-5 h-5 rounded-full flex items-center justify-center text-[9px] font-bold
                                        {{ $status === 'present' ? 'bg-emerald-100 text-emerald-700' : ($status === 'absent' ? 'bg-rose-100 text-rose-500' : 'bg-slate-100 text-slate-300') }}">
                                        {{ $status === 'present' ? '✓' : ($status === 'absent' ? '✕' : '–') }}
                                    </div>
                                @else
                                    <div class="w-5 h-5 rounded-full flex items-center justify-center text-[8px] font-bold
                                        {{ $status === 'present' ? 'bg-brand-100 text-brand-700' : ($status === 'absent' ? 'bg-rose-100 text-rose-500' : 'bg-slate-100 text-slate-300') }}">
                                        {{ $status === 'present' ? $hrs : ($status === 'absent' ? '0' : '–') }}
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- Stats row --}}
                <div class="px-4 pb-3 flex items-center gap-3 text-xs text-slate-500">
                    @if ($isDaily)
                        <span class="font-semibold text-slate-700">{{ $card['present'] }}</span> days present
                        <span class="text-slate-200">|</span>
                        <span class="font-medium">€{{ $card['rate_label'] }}</span>
                    @else
                        <span class="font-semibold text-slate-700">{{ number_format($card['total_hours'], 1) }}h</span> logged
                        @if ($card['ot_hours'] > 0)
                            <span class="text-emerald-600 font-semibold">+{{ number_format($card['ot_hours'], 1) }}h OT</span>
                        @endif
                        <span class="text-slate-200">|</span>
                        <span class="font-medium">€{{ $card['rate_label'] }}</span>
                    @endif
                </div>

                {{-- Footer action --}}
                <div class="mt-auto px-4 py-3 border-t border-slate-100 flex items-center gap-2">
                    <a href="{{ $attendanceUrl }}"
                        class="flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold transition
                            {{ $locked ? 'bg-slate-50 text-slate-400 cursor-not-allowed' : 'bg-slate-900 text-white hover:bg-slate-800' }}">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V4m8 3V4M4 11h16M6 5h12a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2Z" />
                        </svg>
                        {{ $locked ? 'Week locked' : ($marked ? 'Edit attendance' : 'Mark attendance') }}
                    </a>
                    <a href="{{ route('employees.show', $emp->id) }}"
                        class="inline-flex items-center justify-center px-2.5 py-1.5 rounded-lg text-xs font-semibold text-slate-500 hover:text-slate-700 hover:bg-slate-100 transition"
                        title="View profile">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                        </svg>
                    </a>
                </div>
            </div>
        @endforeach
    </div>
@endif
