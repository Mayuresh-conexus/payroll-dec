{{-- payroll/partials/_settle_modal.blade.php
     One global settlement modal.
     All data references use openSettleModal (the current row index).
     The modal is inside x-if so bindings are safe (openSettleModal is never null here).
--}}
<div x-show="openSettleModal !== null"
     x-transition:enter="ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
     x-transition:leave="ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
     @keydown.escape.window="closeSettle()"
     class="fixed inset-0 z-50 flex items-center justify-center bg-black/30 backdrop-blur-sm"
     style="display:none">

    {{-- Modal card --}}
    <template x-if="openSettleModal !== null">
        <div x-transition:enter="ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
             x-transition:leave="ease-in duration-150" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
             @click.outside="closeSettle()"
             class="bg-white rounded-xl shadow-xl border border-slate-200 w-full max-w-sm mx-4 overflow-hidden">

            {{-- Header --}}
            <div class="flex items-center justify-between px-5 py-4 border-b border-slate-100">
                <div>
                    <h3 class="text-sm font-semibold text-slate-900">Settle Advance</h3>
                    <p class="text-xs text-slate-400 mt-0.5" x-text="items[openSettleModal].employee_name"></p>
                </div>
                <button @click="closeSettle()"
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
                      x-text="'€' + formatMoney(items[openSettleModal].prev_advance_balance)"></span>
            </div>

            {{-- Body --}}
            <div class="px-5 py-4 space-y-4">

                {{-- Recover input --}}
                <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1.5">
                        Amount to recover from cash
                    </label>
                    <div class="flex items-center gap-2">
                        <div class="relative flex-1">
                            <span class="absolute inset-y-0 left-3 flex items-center text-slate-400 text-sm pointer-events-none">€</span>
                            <input id="settle-input"
                                   type="number" min="0" step="0.01"
                                   x-model.number="items[openSettleModal].recover"
                                   @input="setRecover(openSettleModal)"
                                   :max="maxRecover(openSettleModal)"
                                   placeholder="0.00"
                                   class="w-full pl-7 pr-3 py-2 text-right font-mono text-sm border border-slate-200 rounded-lg focus:border-brand-400 focus:ring-2 focus:ring-brand-400/20 outline-none transition">
                        </div>
                        <button type="button"
                                @click="fillMaxRecover(openSettleModal)"
                                class="px-3 py-2 rounded-lg bg-slate-900 text-white text-xs font-semibold hover:bg-slate-700 transition whitespace-nowrap">
                            Max
                        </button>
                    </div>
                    {{-- Max recoverable hint --}}
                    <p class="text-xs text-slate-400 mt-1.5 text-right">
                        Max this week:
                        <span class="font-semibold text-slate-600"
                              x-text="'€' + formatMoney(maxRecover(openSettleModal))"></span>
                        <span class="text-slate-300 mx-1">·</span>
                        Surplus:
                        <span class="font-semibold text-slate-600"
                              x-text="'€' + formatMoney(weeklySurplus(openSettleModal))"></span>
                    </p>
                </div>

                {{-- Cash breakdown --}}
                <div class="rounded-lg border border-slate-100 divide-y divide-slate-100 text-sm overflow-hidden">
                    <div class="flex items-center justify-between px-4 py-2.5 bg-slate-50">
                        <span class="text-xs text-slate-500">Weekly cash available</span>
                        <span class="font-mono text-xs font-medium text-slate-700"
                              x-text="'€' + formatMoney(weeklySurplus(openSettleModal))"></span>
                    </div>
                    <div class="flex items-center justify-between px-4 py-2.5">
                        <span class="text-xs text-slate-500">Recovery deduction</span>
                        <span class="font-mono text-xs font-medium text-red-600"
                              x-text="items[openSettleModal].recover > 0 ? '− €' + formatMoney(items[openSettleModal].recover) : '€0.00'"></span>
                    </div>
                    <div class="flex items-center justify-between px-4 py-2.5 font-semibold"
                         :class="cashAfterRecover(openSettleModal) === 0 ? 'bg-amber-50' : 'bg-white'">
                        <span class="text-xs text-slate-700">Employee receives cash</span>
                        <span class="font-mono text-xs"
                              :class="cashAfterRecover(openSettleModal) === 0 ? 'text-amber-600' : 'text-slate-800'"
                              x-text="'€' + formatMoney(cashAfterRecover(openSettleModal))"></span>
                    </div>
                </div>

                {{-- After-save preview --}}
                <div class="rounded-lg border px-4 py-3 transition-colors"
                     :class="advanceBalance(openSettleModal) === 0 ? 'bg-emerald-50 border-emerald-200' : 'bg-slate-50 border-slate-200'">
                    <template x-if="advanceBalance(openSettleModal) === 0">
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
                    <template x-if="advanceBalance(openSettleModal) > 0">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-xs font-medium text-slate-700">Arrears remaining after save</p>
                                <p class="text-xs text-slate-400 mt-0.5">Can settle more in a future week.</p>
                            </div>
                            <span class="font-mono text-base font-bold text-red-600"
                                  x-text="'€' + formatMoney(advanceBalance(openSettleModal))"></span>
                        </div>
                    </template>
                </div>

            </div>

            {{-- Footer --}}
            <div class="flex items-center justify-between px-5 py-3.5 bg-slate-50 border-t border-slate-100">
                <p class="text-xs text-slate-400">Changes apply when you save payroll.</p>
                <button type="button" @click="closeSettle()"
                        class="px-4 py-2 rounded-lg bg-slate-900 text-white text-sm font-medium hover:bg-slate-700 transition">
                    Done
                </button>
            </div>

        </div>
    </template>

</div>
