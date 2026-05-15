@extends('layouts.app')

@section('title', 'Monthly payroll')
@section('page_title', 'Monthly payroll')
@section('page_header', 'Monthly Payroll')
@section('page_subtitle', 'Summarise and review all weekly payroll runs within a calendar month.')

@section('page_action')
    <form method="get" action="{{ route('payroll.monthly.index') }}" class="flex flex-wrap items-center gap-3">
        <label class="sr-only">Month</label>
        <input type="month" name="month" value="{{ $month }}" class="rounded-lg border-slate-300 shadow-sm text-sm py-2 px-3 focus:ring-slate-500 focus:border-slate-500">
        
        <button type="submit" class="px-4 py-2 rounded-lg bg-slate-900 text-white text-sm font-medium hover:bg-slate-800 transition shadow-sm">
            Load
        </button>

        @php
            $weekSet = [];
            foreach ($rows as $row) {
                preg_match_all('/\d+/', $row['weeks_display'] ?? '', $m);
                foreach ($m[0] as $w) {
                    $weekSet[(int) $w] = true;
                }
            }
            $weeks = array_keys($weekSet);
            sort($weeks);
        @endphp

        @if(!empty($weeks))
            <div class="hidden sm:flex flex-wrap gap-1 items-center ml-2 mr-2">
                @foreach ($weeks as $w)
                    <span class="px-2 py-0.5 font-medium rounded text-[10px] bg-indigo-50 text-indigo-700 border border-indigo-100">WK {{ $w }}</span>
                @endforeach
            </div>
        @endif

        <a href="{{ route('payroll.exportMonthXlsx', ['month' => $month]) }}"
           class="px-3 py-2 rounded-lg border border-slate-300 bg-white text-sm font-medium text-slate-700 hover:bg-slate-50 shadow-sm transition">
            Export XLSX
        </a>
    </form>
@endsection

@section('content')
    <div x-data="monthlyNotes()" x-init="init({{ json_encode($rows->map(function ($r) {return $r['note'] ?? '';})->values()) }})" class="space-y-6">


        <form action="{{ route('payroll.saveMonth') }}" method="post"
            class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
            @csrf
            <input type="hidden" name="month" value="{{ $month }}">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-slate-500 uppercase text-xs font-semibold">
                        <tr>

                            <th class="px-4 py-3 text-left">Code</th>
                            <th class="px-4 py-3 text-left">Name</th>
                            <th class="px-4 py-3 text-center">Type</th>
                            <th class="px-4 py-3 text-right">Weekly</th>
                            <th class="px-4 py-3 text-right">Addons</th>
                            <th class="px-4 py-3 text-right">Gross</th>
                            <th class="px-4 py-3 text-right">Cash</th>
                            <th class="px-4 py-3 text-right">Bank</th>
                            <th class="px-4 py-3 text-right">
                                Arrears
                                <span class="text-slate-400 font-normal normal-case text-xs ml-1"
                                      title="Outstanding advance arrears for this month. Settle here to clear carry-forward.">ⓘ</span>
                            </th>
                            <th class="px-4 py-3 text-left">Transfer ID</th>
                            <th class="px-4 py-3 text-left">Note</th>
                            <th class="px-4 py-3 text-left">Transfer Date</th>
                            <th class="px-4 py-3 text-left">Status</th>
                        </tr>
                    </thead>
                    {{-- Sort rows by employee name --}}
                    @php
                        $rows = $rows
                            ->sortBy(function ($row) {
                                return $row['employee']->name;
                            })
                            ->values();
                    @endphp
                    <tbody class="divide-y divide-slate-100">
                        @forelse($rows as $index => $row)
                            @php
                                $emp = $row['employee'];
                                $weeklyAmount = $row['weekly_amount'] ?? 0;
                                $addonsTotal = 0;
                                $addons = $row['addons'] ?? [];
                                foreach ($addons as $ad) {
                                    $addonsTotal += (float) ($ad['amount'] ?? 0);
                                }
                            @endphp
                        @php
                            $bfTotal    = (float) ($row['bank_fix_total'] ?? 0);
                            $advSettled = (float) ($row['advance_settled'] ?? 0);
                            $advBefore  = (float) ($row['advance_before_settle'] ?? 0);
                            $advBalance = (float) ($row['advance_balance'] ?? 0);
                        @endphp
                            <tr x-data="{ settled: {{ number_format($advSettled, 2, '.', '') }}, bankFix: {{ number_format($bfTotal, 2, '.', '') }} }"
                                @settle-updated.window="if ($event.detail.index === {{ $index }}) settled = $event.detail.amount"
                                class="hover:bg-slate-50/80">
                                <td class="px-4 py-3 font-mono text-xs text-slate-600">{{ $emp->employee_code }}</td>
                                <td class="px-4 py-3 text-sm font-medium text-slate-800">{{ $emp->name }}</td>
                                <td class="px-4 py-3 text-center text-xs">
                                    @if ($row['type'] === 'daily_rate')
                                        <span
                                            class="inline-flex px-2 py-1 rounded-full bg-emerald-50 text-emerald-700">Daily</span>
                                    @else
                                        <span
                                            class="inline-flex px-2 py-1 rounded-full bg-brand-50 text-brand-700">Hourly</span>
                                    @endif
                                </td>

                                <td class="px-4 py-3 text-right text-sm text-slate-800">
                                    {{ number_format($weeklyAmount, 2) }}
                                </td>
                                <td class="px-4 py-3 text-right text-sm text-purple-700 font-semibold bg-purple-50">
                                    {{ number_format($addonsTotal, 2) }}
                                </td>

                                <td class="px-4 py-3 text-right text-sm text-slate-800">
                                    {{ number_format($row['gross_amount'], 2) }}
                                </td>
                                <td class="px-4 py-3 text-right text-sm text-green-700 font-semibold bg-green-50">
                                    {{ number_format($row['cash_amount'] ?? 0, 2) }}
                                </td>
                                <td class="px-4 py-3 text-right text-sm text-orange-700 font-semibold bg-orange-50">
                                    <span x-text="Math.max(0, bankFix - settled).toFixed(2)">{{ number_format($row['bank_amount'], 2) }}</span>
                                </td>

                                {{-- Arrears column --}}
                                <td class="px-4 py-3 align-middle" style="min-width:160px">
                                    @if ($advBefore > 0)
                                        <div class="flex flex-col items-end gap-1.5">
                                            {{-- Status chip: settled vs outstanding --}}
                                            <template x-if="settled >= {{ number_format($advBefore, 2, '.', '') }}">
                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-emerald-50 border border-emerald-200 text-emerald-700 text-xs font-semibold">
                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                                                    Settled
                                                </span>
                                            </template>
                                            <template x-if="settled < {{ number_format($advBefore, 2, '.', '') }}">
                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-red-50 border border-red-200 text-red-700 text-xs font-semibold"
                                                      x-text="'€' + ({{ number_format($advBefore, 2, '.', '') }} - settled).toFixed(2)">
                                                </span>
                                            </template>
                                            {{-- Settle trigger --}}
                                            <button type="button"
                                                    @click="openSettle({{ $index }}, '{{ addslashes($emp->name) }}', {{ number_format($advBefore, 2, '.', '') }}, bankFix, settled)"
                                                    class="inline-flex items-center gap-1 text-xs text-brand-600 hover:text-brand-800 font-medium transition-colors">
                                                Settle
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
                                            </button>
                                        </div>
                                        {{-- Hidden inputs --}}
                                        <input type="hidden" name="items[{{ $index }}][advance_settled]" :value="settled">
                                        <input type="hidden" name="items[{{ $index }}][advance_before_settle]" value="{{ number_format($advBefore, 2, '.', '') }}">
                                    @else
                                        <div class="flex justify-end"><span class="text-slate-300 text-sm">—</span></div>
                                        <input type="hidden" name="items[{{ $index }}][advance_settled]"       value="0">
                                        <input type="hidden" name="items[{{ $index }}][advance_before_settle]" value="0">
                                    @endif
                                </td>

                                <td class="px-4 py-3 text-left">
                                    <input type="text" name="items[{{ $index }}][transfer_id]"
                                        value="{{ $row['transfer_id'] ?? '' }}"
                                        class="rounded-md border border-slate-200 px-2 py-1 text-sm">
                                </td>
                                <td class="px-4 py-3 text-left">
                                    <button type="button" @click="open({{ $index }})"
                                        class="px-3 py-1 rounded-md bg-slate-100 border text-sm">Add Note</button>
                                    <!-- Hidden input to bind the note, x-model will handle the binding -->
                                    <input type="hidden" name="items[{{ $index }}][note]"
                                        x-model="items[{{ $index }}]">
                                </td>



                                <td class="px-4 py-3 text-left">
                                    <input type="date" name="items[{{ $index }}][transfer_date]"
                                        value="{{ $row['transfer_date'] ?? '' }}"
                                        class="rounded-md border border-slate-200 px-2 py-1 text-sm">
                                </td>
                                <td class="px-4 py-3 text-left">
                                    <select name="items[{{ $index }}][transfer_status]"
                                        class="rounded-md border border-slate-200 px-2 py-1 text-sm">
                                        <option value="pending" @selected(($row['transfer_status'] ?? '') === 'pending')>Pending</option>
                                        <option value="completed" @selected(($row['transfer_status'] ?? '') === 'completed')>Completed</option>
                                        <option value="failed" @selected(($row['transfer_status'] ?? '') === 'failed')>Failed</option>
                                    </select>
                                </td>

                                <input type="hidden" name="items[{{ $index }}][employee_id]"
                                    value="{{ $emp->id }}">
                                <input type="hidden" name="items[{{ $index }}][type]" value="{{ $row['type'] }}">
                                <input type="hidden" name="items[{{ $index }}][gross]"
                                    value="{{ $row['gross_amount'] }}">
                                {{-- Bank is reactive: bank_fix minus any settlement entered --}}
                                <input type="hidden" name="items[{{ $index }}][bank]"
                                    :value="Math.max(0, bankFix - settled).toFixed(2)">
                                {{-- bank_fix_total lets saveMonth() know the baseline --}}
                                <input type="hidden" name="items[{{ $index }}][bank_fix_total]"
                                    :value="bankFix.toFixed(2)">
                                <input type="hidden" name="items[{{ $index }}][cash]"
                                    value="{{ $row['cash_amount'] ?? 0 }}">
                                <input type="hidden" name="items[{{ $index }}][weekly_amount]"
                                    value="{{ $weeklyAmount ?? '' }}">
                                <input type="hidden" name="items[{{ $index }}][addons]"
                                    value='{{ json_encode($addons ?? []) }}'>
                                <input type="hidden" name="items[{{ $index }}][overtime]"
                                    value="{{ $row['type'] === 'daily_rate' ? $row['overtime_amount'] ?? 0 : $row['overtime_hours'] ?? 0 }}">
                            </tr>
                        @empty
                            <tr>
                                <td colspan="12" class="px-4 py-6 text-center text-sm text-slate-500">No payroll items
                                    available for this month.</td>
                            </tr>
                        @endforelse
                    </tbody>

                    @if ($rows->count())
                        <tfoot class="bg-slate-50 text-sm">
                            <tr>
                                <td colspan="5" class="px-4 py-3 text-right font-semibold text-slate-700">Totals</td>
                                <td class="px-4 py-3 text-right font-semibold text-green-800">
                                    {{ number_format($totals['gross'], 2) }}</td>
                                <td class="px-4 py-3 text-right font-semibold text-green-800">
                                    {{ number_format($totals['cash'], 2) }}</td>
                                <td class="px-4 py-3 text-right font-semibold text-green-800">
                                    {{ number_format($totals['bank'], 2) }}</td>
                                <td colspan="4"></td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>

            @if ($rows->count())
                <div class="px-4 py-3 border-t border-slate-100 flex justify-end">
                    <button type="submit" class="px-4 py-2 rounded-lg bg-slate-900 text-white text-sm font-medium">Save
                        monthly payroll</button>
                </div>
            @endif
        </form>

        <!-- Note modal -->
        <div x-show="show" x-cloak class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-40">
            <div class="bg-white rounded-lg shadow-lg w-1/2 p-4">
                <h3 class="text-lg font-semibold mb-2">Edit Note</h3>
                <textarea x-model="currentNote" class="w-full h-40 border rounded-md p-2" placeholder="Add a note"></textarea>
                <div class="flex justify-end gap-2 mt-3">
                    <button type="button" @click="close()" class="px-4 py-2 rounded bg-gray-100">Cancel</button>
                    <button type="button" @click="save()"
                        class="px-4 py-2 rounded bg-slate-900 text-white">Save</button>
                </div>
            </div>
        </div>
        <!-- Settle Modal -->
        <div x-show="settleShow" x-cloak 
             x-transition:enter="ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
             x-transition:leave="ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
             @keydown.escape.window="closeSettle()"
             class="fixed inset-0 z-50 flex items-center justify-center bg-black/30 backdrop-blur-sm">
             
            <template x-if="settleShow">
                <div x-transition:enter="ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                     x-transition:leave="ease-in duration-150" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
                     @click.outside="closeSettle()"
                     class="bg-white rounded-xl shadow-xl border border-slate-200 w-full max-w-sm mx-4 overflow-hidden">
                     
                    {{-- Header --}}
                    <div class="flex items-center justify-between px-5 py-4 border-b border-slate-100">
                        <div>
                            <h3 class="text-sm font-semibold text-slate-900">Settle Advance</h3>
                            <p class="text-xs text-slate-400 mt-0.5" x-text="settleName"></p>
                        </div>
                        <button type="button" @click="closeSettle()"
                                class="p-1.5 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>

                    {{-- Outstanding banner --}}
                    <div class="flex items-center justify-between px-5 py-3 bg-red-50 border-b border-red-100">
                        <span class="text-xs font-semibold text-red-600 uppercase tracking-wide">Outstanding this month</span>
                        <span class="font-mono text-base font-bold text-red-700"
                              x-text="'€' + settleAdvBefore.toFixed(2)"></span>
                    </div>

                    {{-- Body --}}
                    <div class="px-5 py-4 space-y-4">
                        {{-- Recover input --}}
                        <div>
                            <label class="block text-xs font-medium text-slate-700 mb-1.5">
                                Amount to recover from bank
                            </label>
                            <div class="flex items-center gap-2">
                                <div class="relative flex-1">
                                    <span class="absolute inset-y-0 left-3 flex items-center text-slate-400 text-sm pointer-events-none">€</span>
                                    <input type="number" min="0" step="0.01"
                                           x-model.number="settleInput"
                                           @input="enforceBounds()"
                                           :max="Math.min(settleAdvBefore, settleBankFix)"
                                           placeholder="0.00"
                                           class="w-full pl-7 pr-3 py-2 text-right font-mono text-sm border border-slate-200 rounded-lg focus:border-brand-400 focus:ring-2 focus:ring-brand-400/20 outline-none transition">
                                </div>
                                <button type="button"
                                        @click="settleInput = Math.min(settleAdvBefore, settleBankFix)"
                                        class="px-3 py-2 rounded-lg bg-slate-900 text-white text-xs font-semibold hover:bg-slate-700 transition whitespace-nowrap">
                                    Max
                                </button>
                            </div>
                            <p class="text-xs text-slate-400 mt-1.5 text-right">
                                Max available bank:
                                <span class="font-semibold text-slate-600"
                                      x-text="'€' + settleBankFix.toFixed(2)"></span>
                            </p>
                        </div>
                        
                        {{-- After-save preview --}}
                        <div class="rounded-lg border px-4 py-3 transition-colors"
                             :class="(settleAdvBefore - settleInput) <= 0 ? 'bg-emerald-50 border-emerald-200' : 'bg-slate-50 border-slate-200'">
                            <template x-if="(settleAdvBefore - settleInput) <= 0">
                                <div class="flex items-center gap-2 text-emerald-700">
                                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
                                    </svg>
                                    <div>
                                        <p class="text-sm font-semibold">Fully cleared after save</p>
                                        <p class="text-xs text-emerald-600/80 mt-0.5">No outstanding arrears remaining.</p>
                                    </div>
                                </div>
                            </template>
                            <template x-if="(settleAdvBefore - settleInput) > 0">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-xs font-medium text-slate-700">Arrears remaining after save</p>
                                    </div>
                                    <span class="font-mono text-base font-bold text-red-600"
                                          x-text="'€' + (settleAdvBefore - settleInput).toFixed(2)"></span>
                                </div>
                            </template>
                        </div>
                    </div>

                    {{-- Footer --}}
                    <div class="flex items-center justify-between px-5 py-3.5 bg-slate-50 border-t border-slate-100">
                        <p class="text-xs text-slate-400">Changes apply when you save payroll.</p>
                        <button type="button" @click="saveSettle()"
                                class="px-4 py-2 rounded-lg bg-slate-900 text-white text-sm font-medium hover:bg-slate-700 transition">
                            Done
                        </button>
                    </div>
                </div>
            </template>
        </div>

        <script>
            function monthlyNotes() {
                return {
                    // note modal state
                    show: false,
                    currentIndex: null,
                    currentNote: '',
                    items: [],

                    // settle modal state
                    settleShow: false,
                    settleIndex: null,
                    settleName: '',
                    settleAdvBefore: 0,
                    settleBankFix: 0,
                    settleInput: 0,

                    init(notes) {
                        this.items = notes ?? [];
                    },

                    open(index) {
                        this.currentIndex = index;
                        this.currentNote = this.items[index] ?? '';
                        this.show = true;
                    },

                    close() {
                        this.show = false;
                    },

                    save() {
                        this.items[this.currentIndex] = this.currentNote;
                        this.close();
                    },

                    openSettle(index, name, advBefore, bankFix, currentSettled) {
                        this.settleIndex = index;
                        this.settleName = name;
                        this.settleAdvBefore = advBefore;
                        this.settleBankFix = bankFix;
                        this.settleInput = currentSettled;
                        this.settleShow = true;
                    },

                    closeSettle() {
                        this.settleShow = false;
                        this.settleIndex = null;
                    },

                    enforceBounds() {
                        let maxAllowed = Math.min(this.settleAdvBefore, this.settleBankFix);
                        if (this.settleInput > maxAllowed) {
                            this.settleInput = maxAllowed;
                        }
                    },

                    saveSettle() {
                        // Max out at valid bounds
                        let val = parseFloat(this.settleInput);
                        if (isNaN(val) || val < 0) val = 0;
                        val = Math.min(val, this.settleAdvBefore, this.settleBankFix);
                        
                        window.dispatchEvent(new CustomEvent('settle-updated', {
                            detail: {
                                index: this.settleIndex,
                                amount: val
                            }
                        }));
                        this.closeSettle();
                    }
                }
            }
        </script>
    </div>
@endsection
