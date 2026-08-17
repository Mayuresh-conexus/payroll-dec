{{-- holidays/partials/_create_modal.blade.php --}}
<div x-show="openCreate" x-cloak x-transition.opacity style="margin-top:0"
    class="fixed inset-0 z-40 flex items-center justify-center bg-black/40">
    <div @click.away="openCreate = false"
        class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-6 space-y-5"
        x-data="{ form: { start_date: '{{ old('start_date') }}', end_date: '{{ old('end_date') }}' } }">

        <div class="flex items-start justify-between">
            <div>
                <h2 class="text-lg font-semibold text-slate-900">Add holiday</h2>
                <p class="mt-1 text-xs text-slate-500">Marked BH in attendance for everyone.</p>
            </div>
            <button type="button" @click="openCreate = false"
                class="rounded-full w-8 h-8 text-slate-400 hover:text-slate-600 hover:bg-slate-100 inline-flex items-center justify-center">✕</button>
        </div>

        @if ($errors->any() && old('_modal') === 'create')
            <div class="rounded-lg bg-rose-50 border border-rose-200 px-3 py-2 text-xs text-rose-700 space-y-1">
                @foreach ($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        <form action="{{ route('holidays.store') }}" method="POST" class="space-y-4">
            @csrf
            <input type="hidden" name="_modal" value="create">

            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Holiday name <span class="text-rose-500">*</span></label>
                <input type="text" name="name" required value="{{ old('name') }}"
                    class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:ring-2 focus:ring-slate-500/60 outline-none"
                    placeholder="St Patrick's Day">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">Start date <span class="text-rose-500">*</span></label>
                    <input type="date" name="start_date" required x-model="form.start_date" @change="syncEndDate(form)"
                        class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:ring-2 focus:ring-slate-500/60 outline-none">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">End date</label>
                    <input type="date" name="end_date" x-model="form.end_date" :min="form.start_date"
                        class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:ring-2 focus:ring-slate-500/60 outline-none">
                </div>
            </div>

            <p class="text-[11px] text-slate-400">
                Pick a start date for a single day. Extend the end date for a longer break — every day in the range counts as a bank holiday.
                Holidays falling on a weekend should be entered on the date they are actually observed.
            </p>

            <div class="flex justify-end gap-3 pt-1">
                <button type="button" @click="openCreate = false"
                    class="px-4 py-2 rounded-lg border border-slate-200 text-sm font-medium text-slate-700 hover:bg-slate-50 transition">
                    Cancel
                </button>
                <button type="submit"
                    class="px-4 py-2 rounded-lg bg-slate-900 text-white text-sm font-medium hover:bg-slate-800 transition">
                    Add holiday
                </button>
            </div>
        </form>
    </div>
</div>
