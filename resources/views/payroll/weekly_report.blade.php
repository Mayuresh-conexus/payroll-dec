@extends('layouts.app')

@section('title', 'Weekly attendance report')
@section('page_title', 'Weekly attendance report')

@section('content')
    <div class="space-y-6">

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
                        <th class="px-4 py-3 text-right">Addons Paid</th>
                        <th class="px-4 py-3 text-right">Addons Pending</th>
                        <th class="px-4 py-3 text-right">Cash</th>
                        <th class="px-4 py-3 text-right">Bank</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @php
                        $dayKeys = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];
                    @endphp

                    @forelse($employees as $employee)
                        @php
                            /** @var \App\Models\DailyRateAttendance|null $att */
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

                            $presentDays =
                                $att->present_days ??
                                collect($daysMap)
                                    ->only(['mon', 'tue', 'wed', 'thu', 'fri', 'sat'])
                                    ->sum();
                            $absentDays = max(0, 6 - $presentDays);
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

                            @foreach ($dayKeys as $key)
                                @php $val = $daysMap[$key] ?? 0; @endphp
                                <td class="px-2 py-3 text-center">
                                    @if ($key === 'sun')
                                        <span
                                            class="inline-flex items-center justify-center px-2 py-1 rounded-full bg-slate-100 text-slate-400 text-[11px]">
                                            OFF
                                        </span>
                                    @else
                                        @if ($val)
                                            <span
                                                class="inline-flex items-center justify-center px-2 py-1 rounded-full bg-emerald-50 text-emerald-700 text-[11px]">
                                                IN
                                            </span>
                                        @else
                                            <span
                                                class="inline-flex items-center justify-center px-2 py-1 rounded-full bg-rose-50 text-rose-700 text-[11px]">
                                                OFF
                                            </span>
                                        @endif
                                    @endif
                                </td>
                            @endforeach

                            <td class="px-4 py-3 text-center text-sm">
                                <span
                                    class="inline-flex items-center justify-center px-2 py-1 rounded-full bg-emerald-50 text-emerald-700 text-xs">
                                    {{ $presentDays }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-center text-sm">
                                <span
                                    class="inline-flex items-center justify-center px-2 py-1 rounded-full bg-amber-50 text-amber-700 text-xs">
                                    {{ $absentDays }}
                                </span>
                            </td>

                            @php
                                $pay = $itemsByEmployee[$employee->id] ?? null;
                                $weeklyAmount = $pay->weekly_amount ?? ($pay->gross_amount ?? null);
                                $addons = $pay->addons ?? null;
                                if (is_string($addons)) {
                                    $addons = json_decode($addons, true);
                                }
                                $addonsTotal = 0;
                                if (is_array($addons)) {
                                    foreach ($addons as $ad) {
                                        $addonsTotal += (float) ($ad['amount'] ?? 0);
                                    }
                                }

                                $paymentCash = (float) ($pay->cash_amount ?? 0);
                                $paymentBank = (float) ($pay->bank_amount ?? 0);

                                // cash applied: weekly first, then addons
                                $appliedCashToWeekly = max(0, min($paymentCash, (float) ($weeklyAmount ?? 0)));
                                $remainingCash = max(0, $paymentCash - $appliedCashToWeekly);
                                $appliedCashToAddons = min($remainingCash, $addonsTotal);

                                // bank applied: remaining weekly first, then addons
                                $appliedBankToWeekly = min(
                                    $paymentBank,
                                    max(0, (float) ($weeklyAmount ?? 0) - $appliedCashToWeekly),
                                );
                                $remainingBank = max(0, $paymentBank - $appliedBankToWeekly);
                                $appliedBankToAddons = min($remainingBank, max(0, $addonsTotal - $appliedCashToAddons));

                                $addonsPaid = $appliedCashToAddons + $appliedBankToAddons;
                                $addonsPending = max(0, $addonsTotal - $addonsPaid);

                                $weeklyPending = max(
                                    0,
                                    (float) ($weeklyAmount ?? 0) - ($appliedCashToWeekly + $appliedBankToWeekly),
                                );
                            @endphp

                            <td class="px-4 py-3 text-right text-sm">
                                {{ $weeklyAmount !== null ? number_format($weeklyAmount, 2) : '-' }}</td>
                            <td class="px-4 py-3 text-right text-sm">
                                @if ($addonsTotal > 0)
                                    <details class="text-sm">
                                        <summary class="cursor-pointer">{{ number_format($addonsTotal, 2) }} ▾</summary>
                                        <div class="mt-2 text-xs text-slate-600">
                                            <div class="font-medium">Addons breakdown</div>
                                            <ul class="mt-1 list-disc list-inside">
                                                @if (is_array($addons) && count($addons))
                                                    @foreach ($addons as $ad)
                                                        <li>{{ $ad['date'] ?? 'n/a' }} —
                                                            {{ number_format((float) ($ad['amount'] ?? 0), 2) }}</li>
                                                    @endforeach
                                                @else
                                                    <li>No addon details</li>
                                                @endif
                                            </ul>

                                            <div class="mt-2">
                                                <div class="text-xs">Cash total:
                                                    <strong>{{ number_format($paymentCash, 2) }}</strong>
                                                </div>
                                                <div class="text-xs">Cash → Weekly:
                                                    <strong>{{ number_format($appliedCashToWeekly, 2) }}</strong>
                                                </div>
                                                <div class="text-xs">Cash → Addons:
                                                    <strong>{{ number_format($appliedCashToAddons, 2) }}</strong>
                                                </div>
                                                <div class="text-xs mt-1">Bank total:
                                                    <strong>{{ number_format($paymentBank, 2) }}</strong>
                                                </div>
                                                <div class="text-xs">Bank → Weekly:
                                                    <strong>{{ number_format($appliedBankToWeekly ?? 0, 2) }}</strong>
                                                </div>
                                                <div class="text-xs">Bank → Addons:
                                                    <strong>{{ number_format($appliedBankToAddons ?? 0, 2) }}</strong>
                                                </div>
                                                <div class="text-xs mt-1">Addons paid:
                                                    <strong>{{ number_format($addonsPaid, 2) }}</strong>
                                                </div>
                                                <div class="text-xs">Addons pending:
                                                    <strong>{{ number_format($addonsPending, 2) }}</strong>
                                                </div>
                                                <div class="text-xs">Weekly pending:
                                                    <strong>{{ number_format($weeklyPending, 2) }}</strong>
                                                </div>
                                            </div>
                                        </div>
                                    </details>
                                @else
                                    -
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right text-sm">
                                {{ $addonsPaid > 0 ? number_format($addonsPaid, 2) : '-' }}</td>
                            <td class="px-4 py-3 text-right text-sm">
                                {{ $addonsPending > 0 ? number_format($addonsPending, 2) : '-' }}</td>
                            <td class="px-4 py-3 text-right text-sm">
                                {{ $pay ? number_format($pay->cash_amount ?? 0, 2) : '-' }}</td>
                            <td class="px-4 py-3 text-right text-sm">
                                {{ $pay ? number_format($pay->bank_amount ?? 0, 2) : '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="18" class="px-4 py-6 text-center text-sm text-slate-500">
                                No daily rate employees or attendance found for this week.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

    </div>
@endsection
