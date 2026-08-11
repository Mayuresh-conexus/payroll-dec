{{-- employees/partials/_status_modal.blade.php — Activate / deactivate with an effective date --}}
@php $deactivating = (bool) $employee->is_active; @endphp

<div x-show="statusModalOpen" x-cloak
    x-transition:enter="ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
    x-transition:leave="ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
    @keydown.escape.window="statusModalOpen = false"
    class="fixed inset-0 z-40 flex items-center justify-center bg-black/40 backdrop-blur-sm">
    <div @click.outside="statusModalOpen = false"
        class="bg-white rounded-xl shadow-xl border border-slate-200 w-full max-w-md mx-4 overflow-hidden">

        <div class="flex items-start gap-3 p-5 border-b border-slate-100">
            <div class="flex-shrink-0 w-9 h-9 rounded-full flex items-center justify-center
                {{ $deactivating ? 'bg-amber-50 border border-amber-200' : 'bg-emerald-50 border border-emerald-200' }}">
                <svg class="w-5 h-5 {{ $deactivating ? 'text-amber-600' : 'text-emerald-600' }}"
                    fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    @if ($deactivating)
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/>
                    @else
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                    @endif
                </svg>
            </div>
            <div>
                <h3 class="text-sm font-semibold text-slate-900">
                    {{ $deactivating ? 'Deactivate' : 'Reactivate' }} {{ $employee->name }}?
                </h3>
                <p class="mt-0.5 text-xs text-slate-500">Applied as soon as you confirm.</p>
            </div>
        </div>

        <form action="{{ route('employees.status.update', $employee) }}" method="POST">
            @csrf
            @method('PUT')
            <input type="hidden" name="is_active" value="{{ $deactivating ? 0 : 1 }}">

            <div class="p-5 space-y-4">
                @if ($deactivating)
                    <div class="rounded-lg bg-amber-50 border border-amber-100 divide-y divide-amber-100">
                        <div class="flex items-start gap-3 px-4 py-3 text-xs text-amber-800">
                            <svg class="w-4 h-4 flex-shrink-0 text-amber-500 mt-px" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495ZM10 5a.75.75 0 0 1 .75.75v3.5a.75.75 0 0 1-1.5 0v-3.5A.75.75 0 0 1 10 5Zm0 9a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" clip-rule="evenodd"/>
                            </svg>
                            Removed from the attendance module — no new attendance can be marked.
                        </div>
                        <div class="flex items-start gap-3 px-4 py-3 text-xs text-amber-800">
                            <svg class="w-4 h-4 flex-shrink-0 text-amber-500 mt-px" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495ZM10 5a.75.75 0 0 1 .75.75v3.5a.75.75 0 0 1-1.5 0v-3.5A.75.75 0 0 1 10 5Zm0 9a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" clip-rule="evenodd"/>
                            </svg>
                            Work from the date below onward is excluded from cash and bank.
                        </div>
                        <div class="flex items-start gap-3 px-4 py-3 text-xs text-amber-800">
                            <svg class="w-4 h-4 flex-shrink-0 text-amber-500 mt-px" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm.75-11.25a.75.75 0 0 0-1.5 0v3.5a.75.75 0 0 0 1.5 0v-3.5ZM10 14a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" clip-rule="evenodd"/>
                            </svg>
                            Attendance already recorded stays as-is; only pay is trimmed.
                        </div>
                    </div>
                @else
                    <div class="rounded-lg bg-emerald-50 border border-emerald-100 px-4 py-3 text-xs text-emerald-800">
                        The employee returns to the attendance module and is included in payroll again.
                    </div>
                @endif

                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">
                        Effective from <span class="text-rose-500">*</span>
                    </label>
                    <input type="date" name="effective_from" required
                        x-model="statusEffectiveFrom" max="{{ now()->toDateString() }}"
                        class="w-full sm:w-56 rounded-lg border border-slate-200 px-3 py-2 text-sm focus:ring-2 focus:ring-slate-500/60 focus:border-slate-500 outline-none">
                    <p class="mt-1 text-[11px] text-slate-400">
                        @if ($deactivating)
                            Pick the last-working-day cut-off. Pay stops from this date; earlier days are unaffected.
                        @else
                            The date the employee returns.
                        @endif
                    </p>
                </div>
            </div>

            <div class="flex items-center justify-end gap-2 px-5 py-4 bg-slate-50 border-t border-slate-100">
                <button type="button" @click="statusModalOpen = false"
                    class="px-4 py-2 rounded-lg border border-slate-200 bg-white text-sm font-medium text-slate-700 hover:bg-slate-50 transition">
                    Cancel
                </button>
                <button type="submit"
                    class="px-4 py-2 rounded-lg text-white text-sm font-medium transition
                        {{ $deactivating ? 'bg-amber-600 hover:bg-amber-700' : 'bg-emerald-600 hover:bg-emerald-700' }}">
                    {{ $deactivating ? 'Deactivate' : 'Reactivate' }}
                </button>
            </div>
        </form>
    </div>
</div>
