{{-- employees/partials/_rate_delete_modal.blade.php — Confirm deleting a single rate history entry --}}
<div x-show="deleteModalOpen" x-cloak
    x-transition:enter="ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
    x-transition:leave="ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
    class="fixed inset-0 z-40 flex items-center justify-center bg-black/40 backdrop-blur-sm">
    <div @click.outside="deleteModalOpen = false"
        class="bg-white rounded-xl shadow-xl border border-slate-200 w-full max-w-md mx-4 overflow-hidden">

        <div class="flex items-start gap-3 p-5 border-b border-slate-100">
            <div class="flex-shrink-0 w-9 h-9 rounded-full bg-rose-50 border border-rose-200 flex items-center justify-center">
                <svg class="w-5 h-5 text-rose-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/>
                </svg>
            </div>
            <div>
                <h3 class="text-sm font-semibold text-slate-900">Delete this rate history entry?</h3>
                <p class="mt-0.5 text-xs text-slate-500 font-mono">
                    <span x-text="deleteTarget?.amount"></span>
                    <span class="text-slate-300">·</span>
                    <span x-text="'effective ' + deleteTarget?.effectiveFrom"></span>
                </p>
            </div>
        </div>

        <div class="p-5 space-y-3">
            <div class="rounded-lg bg-rose-50 border border-rose-100 divide-y divide-rose-100">
                <div class="flex items-center gap-3 px-4 py-3 text-xs text-rose-800">
                    <svg class="w-4 h-4 flex-shrink-0 text-rose-500" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495ZM10 5a.75.75 0 0 1 .75.75v3.5a.75.75 0 0 1-1.5 0v-3.5A.75.75 0 0 1 10 5Zm0 9a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" clip-rule="evenodd"/>
                    </svg>
                    This rate change record will be removed from this employee's history.
                </div>
                <div class="flex items-center gap-3 px-4 py-3 text-xs text-rose-800">
                    <svg class="w-4 h-4 flex-shrink-0 text-rose-500" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495ZM10 5a.75.75 0 0 1 .75.75v3.5a.75.75 0 0 1-1.5 0v-3.5A.75.75 0 0 1 10 5Zm0 9a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" clip-rule="evenodd"/>
                    </svg>
                    This action cannot be undone from this screen.
                </div>
            </div>
        </div>

        <div class="flex items-center justify-end gap-2 px-5 py-4 bg-slate-50 border-t border-slate-100">
            <button type="button" @click="deleteModalOpen = false"
                class="px-4 py-2 rounded-lg border border-slate-200 bg-white text-sm font-medium text-slate-700 hover:bg-slate-50 transition">
                Cancel
            </button>
            <form :action="deleteTarget ? '{{ url('employees/'.$employee->id.'/rates') }}/' + deleteTarget.id : '#'" method="POST">
                @csrf
                @method('DELETE')
                <button type="submit"
                    class="px-4 py-2 rounded-lg bg-rose-600 text-white text-sm font-medium hover:bg-rose-700 transition">
                    Yes, Delete
                </button>
            </form>
        </div>
    </div>
</div>
