{{-- holidays/partials/_edit_modal.blade.php --}}
<div x-show="openEdit" x-cloak x-transition.opacity style="margin-top:0"
    class="fixed inset-0 z-40 flex items-center justify-center bg-black/40">
    <div @click.away="openEdit = false"
        class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-6 space-y-5">

        <div class="flex items-start justify-between">
            <div>
                <h2 class="text-lg font-semibold text-slate-900">Edit holiday</h2>
                <p class="mt-1 text-xs text-slate-500">Changing the dates changes who is paid double.</p>
            </div>
            <button type="button" @click="openEdit = false"
                class="rounded-full w-8 h-8 text-slate-400 hover:text-slate-600 hover:bg-slate-100 inline-flex items-center justify-center">✕</button>
        </div>

        @if ($errors->any() && old('_modal') === 'edit')
            <div class="rounded-lg bg-rose-50 border border-rose-200 px-3 py-2 text-xs text-rose-700 space-y-1">
                @foreach ($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        <form :action="editingHoliday.id ? '{{ url('holidays') }}/' + editingHoliday.id : '#'" method="POST" class="space-y-4">
            @csrf
            @method('PUT')
            <input type="hidden" name="_modal" value="edit">

            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Holiday name <span class="text-rose-500">*</span></label>
                <input type="text" name="name" required x-model="editingHoliday.name"
                    class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:ring-2 focus:ring-slate-500/60 outline-none">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">Start date <span class="text-rose-500">*</span></label>
                    <input type="date" name="start_date" required x-model="editingHoliday.start_date" @change="syncEndDate(editingHoliday)"
                        class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:ring-2 focus:ring-slate-500/60 outline-none">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">End date</label>
                    <input type="date" name="end_date" x-model="editingHoliday.end_date" :min="editingHoliday.start_date"
                        class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:ring-2 focus:ring-slate-500/60 outline-none">
                </div>
            </div>

            <div class="flex justify-end gap-3 pt-1">
                <button type="button" @click="openEdit = false"
                    class="px-4 py-2 rounded-lg border border-slate-200 text-sm font-medium text-slate-700 hover:bg-slate-50 transition">
                    Cancel
                </button>
                <button type="submit"
                    class="px-4 py-2 rounded-lg bg-slate-900 text-white text-sm font-medium hover:bg-slate-800 transition">
                    Save changes
                </button>
            </div>
        </form>
    </div>
</div>
