{{-- payroll/partials/_table.blade.php — Payroll data table: attendance, weekly, cash, bank, arrears --}}
<form action="{{ route('payroll.saveWeek') }}" method="post"
    class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
    @csrf
    <input type="hidden" name="year" value="{{ $year }}">
    <input type="hidden" name="week" value="{{ $week }}">

    @php
        // The row is wider than most screens, so the identity columns pin to the
        // left and the payslip action to the right while the money scrolls between
        // them. Sticky cells need an opaque background of their own — a translucent
        // row hover would let the scrolling columns show through underneath.
        $stickyCode = 'sticky left-0 z-10 bg-white group-hover:bg-slate-50';
        $stickyName = 'sticky left-[92px] z-10 bg-white group-hover:bg-slate-50 shadow-[2px_0_4px_-2px_rgba(15,23,42,0.12)]';
        $stickyAction = 'sticky right-0 z-10 bg-white group-hover:bg-slate-50 shadow-[-2px_0_4px_-2px_rgba(15,23,42,0.12)]';
        $headCode = 'sticky left-0 z-20 bg-slate-50';
        $headName = 'sticky left-[92px] z-20 bg-slate-50 shadow-[2px_0_4px_-2px_rgba(15,23,42,0.12)]';
        $headAction = 'sticky right-0 z-20 bg-slate-50 shadow-[-2px_0_4px_-2px_rgba(15,23,42,0.12)]';
        $footCode = 'sticky left-0 z-10 bg-slate-50';
        $footName = 'sticky left-[92px] z-10 bg-slate-50 shadow-[2px_0_4px_-2px_rgba(15,23,42,0.12)]';
        $footAction = 'sticky right-0 z-10 bg-slate-50 shadow-[-2px_0_4px_-2px_rgba(15,23,42,0.12)]';
        $groupEdge = 'border-l border-slate-200';

        // Bank-holiday pay is cleared once a month, so these two columns only
        // appear on the week that settles a month containing a holiday.
        $showBh = $showBankHoliday ?? false;

        // Likewise the leave column: most weeks nobody is away.
        $showLeaveColumn = $showLeave ?? false;
        $leaveTotal = $showLeaveColumn ? $rows->sum(fn (array $row) => (float) ($row['leave_amount'] ?? 0)) : 0.0;

        $columnCount = 8 + ($showBh ? 2 : 0) + ($showLeaveColumn ? 1 : 0);
    @endphp

    <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-slate-500 uppercase text-xs font-semibold">
                <tr>
                    <th rowspan="2" class="{{ $headCode }} w-[92px] px-3 py-2 text-left"><div class="w-[68px]">Code</div></th>
                    <th rowspan="2" class="{{ $headName }} w-[160px] px-3 py-2 text-left"><div class="w-[136px]">Employee</div></th>
                    <th rowspan="2" class="w-[112px] px-3 py-2 text-left">Attendance</th>
                    <th colspan="3" class="{{ $groupEdge }} px-4 py-2 text-center">Weekly</th>
                    @if ($showBh)
                        <th colspan="2" class="{{ $groupEdge }} px-4 py-2 text-center text-violet-700">
                            Bank Holiday
                            <span class="ml-1 text-violet-300 font-normal normal-case"
                                  title="Double pay for every bank holiday worked this month, cleared in this week. The total and the bank share can both be edited; whatever is left of the total is paid in cash, and shows under Weekly Cash.">&#9432;</span>
                        </th>
                    @endif
                    @if ($showLeaveColumn)
                        <th rowspan="2" class="{{ $groupEdge }} w-[110px] px-4 py-2 text-right text-sky-700">
                            Leave
                            <span class="ml-1 text-sky-300 font-normal normal-case"
                                  title="Paid leave taken this week. Already included in the Weekly total to its left.">&#9432;</span>
                        </th>
                    @endif
                    <th rowspan="2" class="{{ $groupEdge }} w-[130px] px-4 py-2 text-right">
                        Arrears
                        <span class="ml-1 text-slate-400 font-normal normal-case"
                              title="Running advance arrears. Positive = employee has received more than earned (advance outstanding).">&#9432;</span>
                    </th>
                    <th rowspan="2" class="{{ $headAction }} w-[84px] px-3 py-2 text-center">Payslip</th>
                </tr>
                <tr>
                    <th class="{{ $groupEdge }} w-[115px] px-4 py-2 text-right font-medium normal-case">Total</th>
                    <th class="w-[115px] px-4 py-2 text-right font-medium normal-case">Cash</th>
                    <th class="w-[115px] px-4 py-2 text-right font-medium normal-case">Bank</th>
                    @if ($showBh)
                        <th class="{{ $groupEdge }} w-[100px] px-4 py-2 text-right font-medium normal-case text-violet-700">Bank</th>
                        <th class="w-[100px] px-4 py-2 text-right font-medium normal-case text-violet-700">Total</th>
                    @endif
                </tr>
            </thead>

            <tbody class="divide-y divide-slate-100">
                @forelse($rows as $index => $row)
                    @php
                        $emp = $row['employee'];
                        // Whether this row settles a premium at all. Gates both the BH
                        // inputs and the tab under the cash cell, so an ordinary row
                        // carries none of that markup.
                        $rowHasPremium = (float) ($row['bh_amount'] ?? 0) > 0;
                        $rowEditable = ! $run || $run->status !== 'final';
                    @endphp
                    <tr class="group hover:bg-slate-50">
                        <td class="{{ $stickyCode }} px-3 py-3 align-top">
                            <div class="w-[68px]">
                                <div class="font-mono text-xs text-slate-600 truncate" title="{{ $emp->employee_code }}">{{ $emp->employee_code }}</div>
                                @unless ($emp->is_active)
                                    {{-- Status sits under the code rather than beside the
                                         name, so the name column keeps its full width. --}}
                                    <span class="mt-1 inline-flex items-center gap-1 px-1 py-0.5 rounded text-[9px] font-semibold bg-rose-50 text-rose-700"
                                        title="This employee is deactivated. They appear here because attendance was already recorded for this week.">
                                        <span class="w-1 h-1 rounded-full bg-rose-400"></span>Inactive
                                    </span>
                                @endunless
                            </div>
                        </td>
                        <td class="{{ $stickyName }} px-3 py-3 align-top">
                            <div class="w-[136px]">
                            {{-- Type and rate ride under the name: they are reference,
                                 not figures to scan across, and folding them in here
                                 keeps two more money columns on screen. --}}
                            <div class="text-sm font-medium text-slate-800 truncate" title="{{ $emp->name }}">{{ $emp->name }}</div>
                            {{-- Pay type and rate ride in one capsule: they are a single
                                 fact ("what this person is paid"), so they read as one
                                 chip rather than two competing items. Kept neutral so the
                                 only tinted thing on the row is a real status, like Inactive.
                                 Left on one line deliberately — the name truncates at a
                                 fixed width, so it sets how narrow this column can go and
                                 stacking the chip would cost a row of height for nothing. --}}
                            <div class="mt-1">
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-slate-100 text-[11px] text-slate-600 whitespace-nowrap"
                                    title="Rate effective for this payroll week, from rate history">
                                    <span class="font-medium">{{ $row['type'] === 'daily_rate' ? 'Daily' : 'Hourly' }}</span>
                                    <span class="font-mono">&euro;{{ number_format($row['rate'] ?? 0, 2) }}</span>
                                </span>
                            </div>
                            </div>
                        </td>
                        {{-- Attendance stacks rather than running on: the count is what
                             gets scanned down the column, and anything extra about the
                             week sits under it as a badge. Strung together on one line
                             these wrapped mid-phrase ("6/6 days +" / "300.00 OT"), which
                             is what was forcing the column wide. --}}
                        <td class="px-3 py-3 text-left align-top">
                            @php
                                $isDailyRow = $row['type'] === 'daily_rate';
                                $hasSunday = ! empty($row['sun_present']);
                                $sundayHours = (float) ($row['sun_hours'] ?? 0);
                                $otAmount = (float) ($row['overtime_amount'] ?? 0);
                                $otHours = (float) ($row['overtime_hours'] ?? 0);
                                $badge = 'inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold whitespace-nowrap';
                            @endphp

                            <div class="w-[88px] space-y-1">
                                <div class="text-xs text-slate-600 whitespace-nowrap">
                                    @if ($isDailyRow)
                                        <span class="font-semibold text-slate-800">{{ $hasSunday ? $row['present_days'] - 1 : $row['present_days'] }}/{{ $row['total_days'] }}</span> days
                                    @else
                                        <span class="font-semibold text-slate-800">{{ (float) ($hasSunday ? $row['total_hours'] - $sundayHours : $row['total_hours']) }}</span> hrs
                                    @endif
                                </div>

                                @if ($hasSunday || $otAmount > 0 || $otHours > 0)
                                    <div class="flex flex-wrap gap-1">
                                        @if ($isDailyRow && $otAmount > 0)
                                            <span class="{{ $badge }} bg-purple-50 text-purple-700" title="Overtime paid this week">
                                                +{{ number_format($otAmount, 2) }} OT
                                            </span>
                                        @endif
                                        @if (! $isDailyRow && $otHours > 0)
                                            <span class="{{ $badge }} bg-purple-50 text-purple-700" title="Overtime hours this week">
                                                +{{ (float) $otHours }}h OT
                                            </span>
                                        @endif
                                        @if ($hasSunday)
                                            <span class="{{ $badge }} bg-orange-50 text-orange-700" title="Worked on Sunday">
                                                @if ($isDailyRow) Sun @else {{ (float) min(6, $sundayHours) }}h Sun @endif
                                            </span>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        </td>
                        <td class="{{ $groupEdge }} px-4 py-3 text-right text-sm text-slate-800">
                            <span x-text="formatMoney(items[{{ $index }}].weekly_amount)"></span>
                            {{-- Zero-earnings warning: no pay this week but bank will still transfer --}}
                            <template x-if="earnings({{ $index }}) === 0 && items[{{ $index }}].bank > 0">
                                <div class="mt-1 flex items-center justify-end gap-1">
                                    <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded bg-amber-50 border border-amber-200 text-amber-700 text-xs font-medium"
                                          title="No earnings this week. The bank transfer will be recorded as an advance. Set bank to €0 to skip it.">
                                        <svg class="w-3 h-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495ZM10 5a.75.75 0 0 1 .75.75v3.5a.75.75 0 0 1-1.5 0v-3.5A.75.75 0 0 1 10 5Zm0 9a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" clip-rule="evenodd"/>
                                        </svg>
                                        No earnings — bank = advance
                                    </span>
                                </div>
                            </template>
                        </td>
                        <td class="px-4 py-3 text-right align-top">
                            {{-- The bank-holiday cash share is inside this figure rather
                                 than beside it, so it is called out on a tab fixed to the
                                 underside of the cell it is part of — square where the two
                                 meet, rounded where it ends, and in the BH violet so it
                                 reads as the same money as the BH column. --}}
                            <div class="inline-flex flex-col items-stretch w-24">
                                @if ($run && $run->status === 'final')
                                    <span class="font-mono text-sm text-slate-700 py-1.5 px-3 text-right"
                                        x-text="formatMoney(items[{{ $index }}].cash)"></span>
                                @else
                                    <input type="number" :min="cashFloor({{ $index }})" step="0.01"
                                        x-model.number="items[{{ $index }}].cash"
                                        @input="recalcRow({{ $index }})"
                                        @focus="$event.target.select()"
                                        @keydown.enter.prevent="focusSiblingRow($event, $event.shiftKey ? -1 : 1)"
                                        data-col="cash" data-row="{{ $index }}"
                                       
                                        :class="items[{{ $index }}].bh_cash > 0 ? 'rounded-t-lg' : 'rounded-lg'"
                                        :title="cashFloor({{ $index }}) > 0
                                            ? 'Cannot go below ' + formatMoney(cashFloor({{ $index }})) + ' — the cash share of the bank-holiday premium.'
                                            : null"
                                        class="no-spinner w-full text-right border border-slate-200 px-3 py-1.5 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500/30 outline-none transition-all shadow-sm">
                                @endif

                                {{-- Follows the BH cash figure, which the admin can now
                                     change, so it is bound rather than printed once. --}}
                                @if ($rowHasPremium)
                                    <template x-if="items[{{ $index }}].bh_cash > 0">
                                        <span class="w-full rounded-b-lg border border-t-0 border-violet-200 bg-violet-50 px-3 py-0.5 text-right text-[10px] font-semibold leading-tight text-violet-700"
                                            :title="'Includes ' + formatMoney(items[{{ $index }}].bh_cash) + ' of bank-holiday cash. Already counted in this figure — the cash cannot be set below it.'">
                                            +<span x-text="formatMoney(items[{{ $index }}].bh_cash)"></span> BH
                                        </span>
                                    </template>
                                @endif
                            </div>
                        </td>
                        <td class="px-4 py-3 text-right align-top">
                            @if ($run && $run->status === 'final')
                                <span class="font-mono text-sm text-slate-700" x-text="formatMoney(items[{{ $index }}].bank)"></span>
                            @else
                                <input type="number" min="0" step="0.01"
                                    x-model.number="items[{{ $index }}].bank"
                                    @input="updateFromBank({{ $index }})"
                                    @focus="$event.target.select()"
                                    @keydown.enter.prevent="focusSiblingRow($event, $event.shiftKey ? -1 : 1)"
                                    data-col="bank" data-row="{{ $index }}"
                                    class="no-spinner w-24 text-right border border-slate-200 rounded-lg px-3 py-1.5 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500/30 outline-none transition-all shadow-sm">
                            @endif
                        </td>

                        {{-- BH — two inputs and a derived remainder. The total is
                             normally what the holidays worked come to, and the bank
                             side is normally the employee's percentage of it; either
                             can be set by hand. Cash is never edited here because it
                             is not paid here — it appears under Weekly Cash, which is
                             where it actually reaches the employee. --}}
                        @if ($showBh)
                            @php
                                $bhEditable = $rowEditable && ((float) ($row['bh_amount'] ?? 0) > 0 || $rowHasPremium);
                                $bhInput = 'no-spinner w-20 text-right font-mono text-sm text-violet-700 border border-violet-200 rounded-lg px-2 py-1.5 bg-violet-50/40 focus:border-violet-500 focus:ring-1 focus:ring-violet-500/30 outline-none transition-all shadow-sm';
                            @endphp

                            {{-- Bank --}}
                            <td class="{{ $groupEdge }} px-4 py-3 text-right align-top">
                                @if ($bhEditable)
                                    <input type="number" min="0" step="0.01"
                                        x-model.number="items[{{ $index }}].bh_bank"
                                        @input="updateBhBank({{ $index }})"
                                        @focus="$event.target.select()"
                                        @keydown.enter.prevent="focusSiblingRow($event, $event.shiftKey ? -1 : 1)"
                                        data-col="bh_bank" data-row="{{ $index }}"
                                        title="The share transferred to the bank, on top of the weekly bank amount. The rest of the total is paid in cash."
                                        class="{{ $bhInput }}">

                                    {{-- Shown while editing, not discovered afterwards: this
                                         is a figure the admin did not type being changed. --}}
                                    <template x-if="items[{{ $index }}].bh_bank_reduced > 0">
                                        <div class="mt-1 flex items-center justify-end">
                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded bg-rose-50 border border-rose-200 text-rose-700 text-[10px] font-semibold whitespace-nowrap"
                                                :title="'The total is smaller than the bank share, so ' + formatMoney(items[{{ $index }}].bh_bank_reduced) + ' was taken off the bank to fit. Cash is 0.'">
                                                bank cut <span class="ml-0.5" x-text="formatMoney(items[{{ $index }}].bh_bank_reduced)"></span>
                                            </span>
                                        </div>
                                    </template>
                                @elseif ($rowHasPremium)
                                    <span class="font-mono text-sm text-violet-700">{{ number_format($row['bh_bank'], 2) }}</span>
                                @else
                                    <span class="text-slate-300 text-sm select-none">—</span>
                                @endif
                            </td>
                            {{-- Total --}}
                            <td class="px-4 py-3 text-right align-top">
                                @if ($bhEditable)
                                    <input type="number" min="0" step="0.01"
                                        x-model.number="items[{{ $index }}].bh_amount"
                                        @input="updateBhTotal({{ $index }})"
                                        @focus="$event.target.select()"
                                        @keydown.enter.prevent="focusSiblingRow($event, $event.shiftKey ? -1 : 1)"
                                        data-col="bh_total" data-row="{{ $index }}"
                                        title="The whole premium. Normally double pay for the holidays worked; raise or lower it to pay something else, and the difference goes to cash."
                                        class="{{ $bhInput }}">

                                    {{-- What the change was, not just that there was one:
                                         an audit reader needs the number. --}}
                                    <template x-if="bhDelta({{ $index }}) !== 0">
                                        <div class="mt-1 flex items-center justify-end gap-1">
                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded bg-amber-50 border border-amber-200 text-amber-700 text-[10px] font-semibold whitespace-nowrap"
                                                :title="'Set by hand. The holidays worked come to ' + formatMoney(items[{{ $index }}].bh_amount_derived) + '. Refreshing the week keeps this.'">
                                                <span x-text="(bhDelta({{ $index }}) > 0 ? '+' : '') + formatMoney(bhDelta({{ $index }}))"></span>
                                            </span>
                                            <button type="button" @click="resetBhSplit({{ $index }})"
                                                class="text-[10px] font-medium text-slate-400 hover:text-violet-700 transition"
                                                title="Put the premium back to the holidays worked">
                                                reset
                                            </button>
                                        </div>
                                    </template>
                                @elseif ($rowHasPremium)
                                    <span class="font-mono text-sm text-violet-700">{{ number_format($row['bh_amount'], 2) }}</span>
                                @else
                                    <span class="text-slate-300 text-sm select-none">—</span>
                                @endif
                            </td>

                        @endif

                        {{-- Leave — paid at the week's own rate and already inside the
                             Weekly total, so it is shown as a breakdown, not edited. --}}
                        @if ($showLeaveColumn)
                            <td class="{{ $groupEdge }} px-4 py-3 text-right align-top">
                                @if (($row['leave_amount'] ?? 0) > 0)
                                    <div class="font-mono text-sm text-sky-700">{{ number_format($row['leave_amount'], 2) }}</div>
                                    <div class="mt-0.5 text-[11px] text-sky-600">
                                        {{ $row['type'] === 'daily_rate'
                                            ? (float) ($row['leave_days'] ?? 0).' d'
                                            : (float) ($row['leave_hours'] ?? 0).' hrs' }}
                                    </div>
                                @else
                                    <span class="text-slate-300 text-sm select-none">—</span>
                                @endif
                            </td>
                        @endif

                        {{-- Arrears column — compact badges only, detail opens in modal --}}
                        <td class="{{ $groupEdge }} px-4 py-3 text-right align-middle">

                            {{-- No advance --}}
                            <template x-if="items[{{ $index }}].prev_advance_balance === 0 && advanceBalance({{ $index }}) === 0">
                                <span class="text-slate-300 text-sm select-none">—</span>
                            </template>

                            {{-- New advance given this week --}}
                            <template x-if="items[{{ $index }}].prev_advance_balance === 0 && advanceBalance({{ $index }}) > 0">
                                <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full bg-orange-50 border border-orange-200 text-orange-700 text-xs font-semibold cursor-default"
                                      title="Advance will carry to next week">
                                    <svg class="w-3 h-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495ZM10 5a.75.75 0 0 1 .75.75v3.5a.75.75 0 0 1-1.5 0v-3.5A.75.75 0 0 1 10 5Zm0 9a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" clip-rule="evenodd"/>
                                    </svg>
                                    <span x-text="'€' + formatMoney(advanceBalance({{ $index }}))"></span>
                                </span>
                            </template>

                            {{-- Prior arrears — compact chip + Settle button --}}
                            <template x-if="items[{{ $index }}].prev_advance_balance > 0">
                                <div class="flex flex-col items-end gap-1.5">
                                    {{-- Status chip: settled vs outstanding --}}
                                    <template x-if="advanceBalance({{ $index }}) === 0">
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-emerald-50 border border-emerald-200 text-emerald-700 text-xs font-semibold">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                                            Settled
                                        </span>
                                    </template>
                                    <template x-if="advanceBalance({{ $index }}) > 0">
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-red-50 border border-red-200 text-red-700 text-xs font-semibold"
                                              x-text="'€' + formatMoney(advanceBalance({{ $index }}))">
                                        </span>
                                    </template>
                                    {{-- Settle trigger — hidden when finalized --}}
                                    @if (!$run || $run->status !== 'final')
                                        <button type="button"
                                                @click="openSettle({{ $index }})"
                                                class="inline-flex items-center gap-1 text-xs text-brand-600 hover:text-brand-800 font-medium transition-colors">
                                            Settle
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
                                        </button>
                                    @endif
                                </div>
                            </template>

                        </td>

                        {{-- Payslip PDF download --}}
                        <td class="{{ $stickyAction }} px-3 py-3 text-center align-top">
                            <a href="{{ route('payroll.payslip', ['year' => $year, 'week' => $week, 'employee' => $emp->id]) }}"
                                target="_blank"
                                title="Download payslip for {{ $emp->name }}"
                                class="inline-flex items-center justify-center w-7 h-7 rounded-lg border border-slate-200 text-slate-400 hover:text-slate-700 hover:border-slate-400 hover:bg-slate-50 transition">
                                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0-3-3m3 3 3-3M3 17v3a1 1 0 0 0 1 1h16a1 1 0 0 0 1-1v-3" />
                                </svg>
                            </a>
                        </td>
                        {{-- Hidden inputs for submit — use :value (one-way reactive) not x-model --}}
                        <td class="hidden">
                            <input type="hidden" name="items[{{ $index }}][employee_id]"              value="{{ $emp->id }}">
                            <input type="hidden" name="items[{{ $index }}][type]"                     value="{{ $row['type'] }}">
                            <input type="hidden" name="items[{{ $index }}][weekly_amount]"            :value="items[{{ $index }}].weekly_amount">
                            <input type="hidden" name="items[{{ $index }}][gross]"                    :value="items[{{ $index }}].gross_amount">
                            <input type="hidden" name="items[{{ $index }}][cash]"                     :value="items[{{ $index }}].cash">
                            <input type="hidden" name="items[{{ $index }}][bank]"                     :value="items[{{ $index }}].bank">
                            {{-- The premium is derived and posts as it was calculated; the
                                 split follows whatever the admin left in the two boxes. --}}
                            <input type="hidden" name="items[{{ $index }}][bh_amount]"                value="{{ $row['bh_amount_derived'] ?? $row['bh_amount'] ?? 0 }}">
                            <input type="hidden" name="items[{{ $index }}][bh_cash]"                  :value="items[{{ $index }}].bh_cash">
                            <input type="hidden" name="items[{{ $index }}][bh_bank]"                  :value="items[{{ $index }}].bh_bank">
                            <input type="hidden" name="items[{{ $index }}][bh_amount_override]"       :value="items[{{ $index }}].bh_amount_override ?? ''">
                            <input type="hidden" name="items[{{ $index }}][bh_bank_override]"         :value="items[{{ $index }}].bh_bank_override ?? ''">
                            <input type="hidden" name="items[{{ $index }}][leave_days]"               value="{{ $row['leave_days'] ?? 0 }}">
                            <input type="hidden" name="items[{{ $index }}][leave_hours]"              value="{{ $row['leave_hours'] ?? 0 }}">
                            <input type="hidden" name="items[{{ $index }}][leave_amount]"             value="{{ $row['leave_amount'] ?? 0 }}">
                            <input type="hidden" name="items[{{ $index }}][addons]"                   :value="JSON.stringify(items[{{ $index }}].addons || [])">
                            <input type="hidden" name="items[{{ $index }}][addons_selected_dates]"    :value="JSON.stringify(items[{{ $index }}].selectedAddons || [])">
                            <input type="hidden" name="items[{{ $index }}][total_days]"               value="{{ $row['total_days'] }}">
                            <input type="hidden" name="items[{{ $index }}][present_days]"             value="{{ $row['present_days'] }}">
                            <input type="hidden" name="items[{{ $index }}][total_hours]"              value="{{ $row['total_hours'] }}">
                            <input type="hidden" name="items[{{ $index }}][overtime]"                 value="{{ $row['type'] === 'daily_rate' ? $row['overtime_amount'] ?? 0 : $row['overtime_hours'] ?? 0 }}">
                            <input type="hidden" name="items[{{ $index }}][prev_advance_balance]"     value="{{ $row['prev_advance_balance'] ?? 0 }}">
                            <input type="hidden" name="items[{{ $index }}][bank_transfer_fix_amount]" value="{{ $row['bank_transfer_fix_amount'] ?? 0 }}">
                            <input type="hidden" name="items[{{ $index }}][recover]"                  :value="items[{{ $index }}].recover">
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $columnCount }}" class="px-4 py-6 text-center text-sm text-slate-500">
                            No attendance found for this week. Please fill attendance first.
                        </td>
                    </tr>
                @endforelse
            </tbody>

            @if ($rows->count())
                <tfoot class="bg-slate-50 text-sm">
                    <tr>
                        {{-- Mirrors the header's sticky columns so "Totals" stays put
                             and the figures never drift out of their columns. --}}
                        <td class="{{ $footCode }} px-3 py-3"></td>
                        <td class="{{ $footName }} px-3 py-3 text-right font-semibold text-slate-700">Totals</td>
                        <td class="px-3 py-3"></td>{{-- attendance spacer --}}
                        <td class="{{ $groupEdge }} px-4 py-3 text-right font-semibold text-slate-800"><span x-text="formatMoney(totals.weeklyAmount)"></span></td>
                        <td class="px-4 py-3 text-right font-semibold text-slate-800"><span x-text="formatMoney(totals.cash)"></span></td>
                        <td class="px-4 py-3 text-right font-semibold text-slate-800"><span x-text="formatMoney(totals.bank)"></span></td>
                        @if ($showBh)
                            <td class="{{ $groupEdge }} px-4 py-3 text-right font-semibold text-violet-700"><span x-text="formatMoney(totals.bhBank)"></span></td>
                            <td class="px-4 py-3 text-right font-semibold text-violet-700"><span x-text="formatMoney(totals.bhAmount)"></span></td>
                        @endif
                        @if ($showLeaveColumn)
                            <td class="{{ $groupEdge }} px-4 py-3 text-right font-semibold text-sky-700">{{ number_format($leaveTotal, 2) }}</td>
                        @endif
                        <td class="{{ $groupEdge }} px-4 py-3"></td>{{-- arrears spacer --}}
                        <td class="{{ $footAction }} px-3 py-3"></td>{{-- payslip spacer --}}
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>

    @if ($rows->count())
        @include('payroll.partials._cash_summary')

        @if (!$run || $run->status !== 'final')
            <div class="px-4 py-3 border-t border-slate-100 flex justify-end">
                <button type="submit"
                    class="px-4 py-2 rounded-lg bg-slate-900 text-white text-sm font-medium hover:bg-slate-800">
                    Save weekly payroll
                </button>
            </div>
        @endif
    @endif
</form>
