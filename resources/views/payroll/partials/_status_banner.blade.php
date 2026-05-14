{{-- payroll/partials/_status_banner.blade.php — Finalization status (final / draft + finalize + refresh) --}}

{{-- Success flash --}}
@if (session('success'))
    <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)"
         x-transition:leave="transition ease-in duration-300" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
         class="rounded-lg bg-emerald-50 border border-emerald-200 px-4 py-3 text-sm text-emerald-800 flex items-center justify-between gap-3">
        <div class="flex items-center gap-2">
            <svg class="w-4 h-4 text-emerald-600 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
            </svg>
            {{ session('success') }}
        </div>
        <button @click="show = false" class="text-emerald-500 hover:text-emerald-700">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
        </button>
    </div>
@endif

@if ($run)
    @if ($run->status === 'final')
        <div class="rounded-lg bg-emerald-50 border border-emerald-200 px-4 py-3 text-sm text-emerald-800 flex items-center gap-3">
            <svg class="w-5 h-5 flex-shrink-0 text-emerald-600" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.955 11.955 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" />
            </svg>
            <div class="flex-1">
                <strong>Week {{ $week }}/{{ $year }} is finalized</strong> — this payroll is locked and cannot be edited.
            </div>
        </div>
    @else
        {{-- Refresh warning modal --}}
        <div x-data="{ open: false }"
             @keydown.escape.window="open = false">

            <div class="rounded-lg bg-slate-50 border border-slate-200 px-4 py-3 text-sm text-slate-700 flex items-center justify-between gap-3">
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center gap-1.5 px-2 py-1 rounded-full text-xs bg-amber-50 text-amber-700 border border-amber-100">
                        <span class="w-1.5 h-1.5 rounded-full bg-amber-400"></span> Draft
                    </span>
                    <span class="text-slate-500">Week {{ $week }}/{{ $year }} — save edits below, then finalize when approved.</span>
                </div>

                <div class="flex items-center gap-2">
                    {{-- Refresh button --}}
                    <button type="button" @click="open = true"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-slate-300 bg-white text-xs font-medium text-slate-600 hover:bg-slate-50 hover:border-slate-400 transition shadow-sm">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99"/>
                        </svg>
                        Recalculate from Attendance
                    </button>

                    {{-- Finalize button --}}
                    <form action="{{ route('payroll.finalizeWeek') }}" method="POST"
                        onsubmit="return confirm('Finalize payroll for Week {{ $week }}/{{ $year }}? This will lock it permanently.')">
                        @csrf
                        <input type="hidden" name="year" value="{{ $year }}">
                        <input type="hidden" name="week" value="{{ $week }}">
                        <button type="submit"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-emerald-700 text-white text-xs font-medium hover:bg-emerald-800 transition shadow-sm">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
                            </svg>
                            Finalize Week
                        </button>
                    </form>
                </div>
            </div>

            {{-- Warning modal overlay --}}
            <div x-show="open" x-transition:enter="ease-out duration-200" x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100" x-transition:leave="ease-in duration-150"
                 x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
                 class="fixed inset-0 z-40 flex items-center justify-center bg-black/40 backdrop-blur-sm"
                 style="display: none;">
                <div x-show="open" x-transition:enter="ease-out duration-200"
                     x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                     x-transition:leave="ease-in duration-150" x-transition:leave-start="opacity-100 scale-100"
                     x-transition:leave-end="opacity-0 scale-95"
                     class="bg-white rounded-xl shadow-xl border border-slate-200 w-full max-w-md mx-4 overflow-hidden">

                    {{-- Modal header --}}
                    <div class="flex items-start gap-3 p-5 border-b border-slate-100">
                        <div class="flex-shrink-0 w-9 h-9 rounded-full bg-amber-50 border border-amber-200 flex items-center justify-center">
                            <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/>
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-sm font-semibold text-slate-900">Recalculate from Attendance?</h3>
                            <p class="mt-0.5 text-xs text-slate-500">Week {{ $week }}/{{ $year }}</p>
                        </div>
                    </div>

                    {{-- Modal body --}}
                    <div class="p-5 space-y-3 text-sm text-slate-600">
                        <p>This will <strong class="text-slate-800">recalculate all weekly totals</strong> from the current attendance data.</p>
                        <ul class="space-y-1.5 text-xs text-slate-500">
                            <li class="flex items-start gap-2">
                                <svg class="w-3.5 h-3.5 mt-0.5 text-amber-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495ZM10 5a.75.75 0 0 1 .75.75v3.5a.75.75 0 0 1-1.5 0v-3.5A.75.75 0 0 1 10 5Zm0 9a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" clip-rule="evenodd"/></svg>
                                Any manually adjusted cash/bank values will be <strong class="text-slate-700">reset to employee defaults</strong>.
                            </li>
                            <li class="flex items-start gap-2">
                                <svg class="w-3.5 h-3.5 mt-0.5 text-amber-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495ZM10 5a.75.75 0 0 1 .75.75v3.5a.75.75 0 0 1-1.5 0v-3.5A.75.75 0 0 1 10 5Zm0 9a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" clip-rule="evenodd"/></svg>
                                This action <strong class="text-slate-700">cannot be undone</strong>. Review carefully before proceeding.
                            </li>
                        </ul>
                    </div>

                    {{-- Modal footer --}}
                    <div class="flex items-center justify-end gap-2 px-5 py-4 bg-slate-50 border-t border-slate-100">
                        <button type="button" @click="open = false"
                            class="px-4 py-2 rounded-lg border border-slate-200 bg-white text-sm font-medium text-slate-700 hover:bg-slate-50 transition">
                            Cancel
                        </button>
                        <form action="{{ route('payroll.refreshWeek') }}" method="POST">
                            @csrf
                            <input type="hidden" name="year" value="{{ $year }}">
                            <input type="hidden" name="week" value="{{ $week }}">
                            <button type="submit"
                                class="px-4 py-2 rounded-lg bg-amber-600 text-white text-sm font-medium hover:bg-amber-700 transition">
                                Yes, Recalculate
                            </button>
                        </form>
                    </div>

                </div>
            </div>
        </div>
    @endif
@endif
