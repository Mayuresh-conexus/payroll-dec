@extends('layouts.app')

@section('title', 'Weekly attendance report')
@section('page_title', 'Weekly attendance report')

@section('content')
    <div class="space-y-6" x-data="{ openModal: null }">

        {{-- Filters and export button --}}
        <form method="get" action="{{ route('payroll.weekly.report') }}"
            class="bg-white rounded-xl shadow-sm border border-slate-200 p-4 flex flex-wrap items-center gap-4 text-sm">
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
                <label class="block text-xs font-semibold text-slate-600 mb-1">Week</label>
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
                    Load report
                </button>

                <a href="{{ route('payroll.weekly.report.csv', ['year' => $year, 'week' => $week]) }}"
                    class="inline-flex items-center px-4 py-2 rounded-lg border border-slate-200 text-sm font-medium text-slate-700 hover:bg-slate-50">
                    Download CSV
                </a>
            </div>
        </form>

        {{-- Table --}}
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-slate-500 uppercase text-xs font-semibold">
                    <tr>
                        <th class="px-4 py-3 text-left">Code</th>
                        <th class="px-4 py-3 text-left">Name</th>
                        <th class="px-4 py-3 text-left">Department</th>
                        <th class="px-4 py-3 text-center">Mon</th>
                        <th class="px-4 py-3 text-center">Tue</th>
                        <th class="px-4 py-3 text-center">Wed</th>
                        <th class="px-4 py-3 text-center">Thu</th>
                        <th class="px-4 py-3 text-center">Fri</th>
                        <th class="px-4 py-3 text-center">Sat</th>
                        <th class="px-4 py-3 text-center">Sun</th>
                        <th class="px-4 py-3 text-center">Present</th>
                        <th class="px-4 py-3 text-center">Absent</th>
                        <th class="px-4 py-3 text-right">Weekly</th>
                        <th class="px-4 py-3 text-right">Addons</th>
                        <th class="px-4 py-3 text-right">Cash</th>
                        <th class="px-4 py-3 text-right">Bank</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @php
                        $dayKeys = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];
                    @endphp

                    @foreach ($employees as $employee)
                        @php
                            $att = $attendance[$employee->id] ?? null;
                            $daysMap = [
                                'mon' => 1,
                                'tue' => 1,
                                'wed' => 1,
                                'thu' => 1,
                                'fri' => 1,
                                'sat' => 1,
                                'sun' => 0,
                            ];
                            if ($att && is_array($att->days_map)) {
                                $daysMap = array_merge($daysMap, $att->days_map);
                            }

                            $presentDays = collect($daysMap)
                                ->only(['mon', 'tue', 'wed', 'thu', 'fri', 'sat'])
                                ->sum();
                            $absentDays = max(0, 6 - $presentDays);
                        @endphp

                        <tr class="hover:bg-slate-50/80">
                            <td class="px-4 py-3 font-mono text-xs text-slate-600">{{ $employee->employee_code }}</td>
                            <td class="px-4 py-3 text-sm font-medium text-slate-800">{{ $employee->name }}</td>
                            <td class="px-4 py-3 text-sm text-slate-600">{{ $employee->department ?? 'No dept' }}</td>

                            @foreach ($dayKeys as $key)
                                @php $val = $daysMap[$key] ?? 0; @endphp
                                <td class="px-2 py-3 text-center">
                                    @if ($key === 'sun')
                                        <span
                                            class="inline-flex items-center justify-center px-2 py-1 rounded-full bg-slate-100 text-slate-400 text-[11px]">OFF</span>
                                    @else
                                        @if ($val)
                                            <span
                                                class="inline-flex items-center justify-center px-2 py-1 rounded-full bg-emerald-50 text-emerald-700 text-[11px]">IN</span>
                                        @else
                                            <span
                                                class="inline-flex items-center justify-center px-2 py-1 rounded-full bg-rose-50 text-rose-700 text-[11px]">OFF</span>
                                        @endif
                                    @endif
                                </td>
                            @endforeach

                            <td class="px-4 py-3 text-center text-sm">
                                <span
                                    class="inline-flex items-center justify-center px-2 py-1 rounded-full bg-emerald-50 text-emerald-700 text-xs">{{ $presentDays }}</span>
                            </td>
                            <td class="px-4 py-3 text-center text-sm">
                                <span
                                    class="inline-flex items-center justify-center px-2 py-1 rounded-full bg-amber-50 text-amber-700 text-xs">{{ $absentDays }}</span>
                            </td>

                            @php
                                $pay = $itemsByEmployee[$employee->id] ?? null;
                                $addons = $pay->addons ?? [];
                                $addonsTotal = collect($addons)->sum('amount');
                            @endphp

                            <td class="px-4 py-3 text-right text-sm">{{ number_format($pay->weekly_amount ?? 0, 2) }}</td>

                            <td class="px-4 py-3 text-right text-sm">
                                @if ($addonsTotal > 0)
                                    <button type="button" class="text-sky-600 hover:text-sky-800 font-medium"
                                        @click="openModal = {{ $employee->id }}">
                                        {{ number_format($addonsTotal, 2) }} ▼
                                    </button>
                                @else
                                    -
                                @endif
                            </td>


                            <td class="px-4 py-3 text-right text-sm">{{ number_format($pay->cash_amount ?? 0, 2) }}</td>
                            <td class="px-4 py-3 text-right text-sm">{{ number_format($pay->bank_amount ?? 0, 2) }}</td>
                        </tr>

                        <!-- Modal for Addon Details -->
                        <div x-show="openModal === {{ $employee->id }}"
                            x-transition:enter="transition ease-out duration-300"
                            x-transition:enter-start="opacity-0 transform scale-95"
                            x-transition:enter-end="opacity-100 transform scale-100"
                            x-transition:leave="transition ease-in duration-200"
                            x-transition:leave-start="opacity-100 transform scale-100"
                            x-transition:leave-end="opacity-0 transform scale-95"
                            class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">

                            <!-- Modal Content -->
                            <div class="bg-white rounded-lg p-6 w-full max-w-md space-y-4 shadow-lg">
                                <div class="font-medium text-lg text-center text-slate-800">Addon Breakdown for
                                    {{ $employee->name }}</div>

                                <!-- Scrollable Content for Addons -->
                                <div class="space-y-4 max-h-72 overflow-y-auto">
                                    @foreach ($addons as $addon)
                                        <div class="flex justify-between items-center px-4 py-2 border-b border-slate-200">
                                            <div class="text-sm text-slate-600">
                                                <strong>{{ \Carbon\Carbon::parse($addon['date'])->format('M d, Y') }}</strong>
                                            </div>
                                            <div class="text-sm text-right text-slate-700">
                                                ${{ number_format($addon['amount'], 2) }}
                                            </div>
                                        </div>
                                    @endforeach
                                </div>

                                <!-- Close Button -->
                                <div class="mt-4 text-right">
                                    <button @click="openModal = null"
                                        class="bg-blue-500 text-white px-4 py-2 rounded-full hover:bg-blue-600">
                                        Close
                                    </button>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection
