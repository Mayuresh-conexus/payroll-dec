{{-- holidays/partials/_delete_modal.blade.php --}}
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
                <h3 class="text-sm font-semibold text-slate-900">Delete this holiday?</h3>
                <p class="mt-0.5 text-xs text-slate-500" x-text="deleteTarget?.name"></p>
            </div>
        </div>

        <div class="p-5">
            <div class="rounded-lg bg-rose-50 border border-rose-100 divide-y divide-rose-100">
                <p class="px-4 py-3 text-xs text-rose-800">These dates stop being marked BH in attendance.</p>
                <p class="px-4 py-3 text-xs text-rose-800">Anyone who worked them loses the double-pay premium once the week is refreshed.</p>
                <p class="px-4 py-3 text-xs text-rose-800">Weeks that are already finalised are protected and will block this.</p>
            </div>
        </div>

        <form :action="deleteTarget ? '{{ url('holidays') }}/' + deleteTarget.id : '#'" method="POST">
            @csrf
            @method('DELETE')
            <div class="flex items-center justify-end gap-2 px-5 py-4 bg-slate-50 border-t border-slate-100">
                <button type="button" @click="deleteModalOpen = false"
                    class="px-4 py-2 rounded-lg border border-slate-200 bg-white text-sm font-medium text-slate-700 hover:bg-slate-50 transition">
                    Cancel
                </button>
                <button type="submit"
                    class="px-4 py-2 rounded-lg bg-rose-600 text-white text-sm font-medium hover:bg-rose-700 transition">
                    Yes, Delete
                </button>
            </div>
        </form>
    </div>
</div>
