{{-- employees/partials/_rate_confirm_modal.blade.php — Confirm a rate change + its effective date --}}
<div x-show="rateConfirmOpen" x-cloak
    x-transition:enter="ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
    x-transition:leave="ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
    @keydown.escape.window="rateConfirmOpen = false"
    class="fixed inset-0 z-40 flex items-center justify-center bg-black/40 backdrop-blur-sm">
    <div @click.outside="rateConfirmOpen = false"
        class="bg-white rounded-xl shadow-xl border border-slate-200 w-full max-w-lg mx-4 overflow-hidden">

        {{-- Header --}}
        <div class="flex items-start gap-3 p-5 border-b border-slate-100">
            <div class="flex-shrink-0 w-9 h-9 rounded-full bg-amber-50 border border-amber-200 flex items-center justify-center">
                <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>
            </div>
            <div>
                <h3 class="text-sm font-semibold text-slate-900">Confirm rate change</h3>
                <p class="mt-0.5 text-xs text-slate-500">Check the effective date before saving.</p>
            </div>
        </div>

        <div class="p-5 space-y-4">

            {{-- What is changing --}}
            <div class="rounded-lg border border-slate-200 divide-y divide-slate-100">
                <template x-for="rate in changedRates" :key="rate.key">
                    <div class="flex items-center justify-between gap-3 px-4 py-3">
                        <span class="text-xs font-medium text-slate-600" x-text="rate.label"></span>
                        <div class="flex items-center gap-2">
                            <span class="text-sm font-mono text-slate-400" x-text="formatAmount(rate.from, rate)"></span>
                            <svg class="w-4 h-4 text-slate-300 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                            </svg>
                            <span class="text-base font-mono font-bold text-slate-900" x-text="formatAmount(rate.to, rate)"></span>
                            <template x-if="rate.pct !== null && rate.delta > 0">
                                <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-bold bg-emerald-50 text-emerald-700">
                                    <svg class="w-2.5 h-2.5" fill="currentColor" viewBox="0 0 20 20"><path d="M10 5 15 13 5 13 Z" /></svg>
                                    <span x-text="Math.abs(rate.pct).toFixed(1) + '%'"></span>
                                </span>
                            </template>
                            <template x-if="rate.pct !== null && rate.delta < 0">
                                <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-bold bg-rose-50 text-rose-700">
                                    <svg class="w-2.5 h-2.5" fill="currentColor" viewBox="0 0 20 20"><path d="M10 15 5 7 15 7 Z" /></svg>
                                    <span x-text="Math.abs(rate.pct).toFixed(1) + '%'"></span>
                                </span>
                            </template>
                        </div>
                    </div>
                </template>
            </div>

            {{-- Effective date — editable right here --}}
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Effective from</label>
                <input type="date" x-model="effectiveFrom"
                    class="w-full sm:w-56 rounded-lg border border-slate-200 px-3 py-2 text-sm focus:ring-2 focus:ring-slate-500/60 focus:border-slate-500 outline-none">
            </div>

            {{-- Contextual warning about the chosen date --}}
            <template x-if="blockingDate">
                <div class="rounded-lg bg-rose-50 border border-rose-200 px-4 py-3 text-xs text-rose-800 flex items-start gap-2">
                    <svg class="w-4 h-4 flex-shrink-0 text-rose-500 mt-px" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495ZM10 5a.75.75 0 0 1 .75.75v3.5a.75.75 0 0 1-1.5 0v-3.5A.75.75 0 0 1 10 5Zm0 9a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" clip-rule="evenodd"/>
                    </svg>
                    <span>
                        This is <strong>backdated</strong>. A newer rate effective
                        <strong x-text="formatDate(blockingDate)"></strong> already exists, so this entry
                        will be recorded in history but will <strong>not</strong> become the current rate.
                    </span>
                </div>
            </template>

            <template x-if="! blockingDate && isEffectiveToday">
                <div class="rounded-lg bg-amber-50 border border-amber-200 px-4 py-3 text-xs text-amber-800 flex items-start gap-2">
                    <svg class="w-4 h-4 flex-shrink-0 text-amber-500 mt-px" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495ZM10 5a.75.75 0 0 1 .75.75v3.5a.75.75 0 0 1-1.5 0v-3.5A.75.75 0 0 1 10 5Zm0 9a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" clip-rule="evenodd"/>
                    </svg>
                    <span>
                        No date was chosen, so this takes effect <strong>immediately</strong> from today
                        (<strong x-text="formatDate(today)"></strong>). Payroll from this date onward will use
                        the new rate. Change the date above if it should start on a different day.
                    </span>
                </div>
            </template>

            <template x-if="! blockingDate && isFutureDated">
                <div class="rounded-lg bg-sky-50 border border-sky-200 px-4 py-3 text-xs text-sky-800 flex items-start gap-2">
                    <svg class="w-4 h-4 flex-shrink-0 text-sky-500 mt-px" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" />
                    </svg>
                    <span>
                        Scheduled for <strong x-text="formatDate(effectiveFrom)"></strong>. The existing rate
                        stays in effect until then.
                    </span>
                </div>
            </template>
        </div>

        {{-- Footer --}}
        <div class="flex items-center justify-end gap-2 px-5 py-4 bg-slate-50 border-t border-slate-100">
            <button type="button" @click="rateConfirmOpen = false"
                class="px-4 py-2 rounded-lg border border-slate-200 bg-white text-sm font-medium text-slate-700 hover:bg-slate-50 transition">
                Cancel
            </button>
            <button type="button" @click="confirmSave()"
                class="px-4 py-2 rounded-lg bg-slate-900 text-white text-sm font-medium hover:bg-slate-800 transition">
                Confirm &amp; Save
            </button>
        </div>
    </div>
</div>
