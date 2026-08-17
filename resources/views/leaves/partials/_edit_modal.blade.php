{{-- leaves/partials/_edit_modal.blade.php --}}
<div x-show="openEdit" x-cloak x-transition.opacity style="margin-top:0"
    class="fixed inset-0 z-40 flex items-center justify-center bg-black/40">
    <div @click.away="openEdit = false"
        class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-6 space-y-5">

        <div class="flex items-start justify-between">
            <div>
                <h2 class="text-lg font-semibold text-slate-900">Edit leave</h2>
                <p class="mt-1 text-xs text-slate-500">Changing the dates changes what is paid and what is deducted.</p>
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

        <form :action="editingLeave.id ? '{{ url('leaves') }}/' + editingLeave.id : '#'" method="POST" class="space-y-4">
            @csrf
            @method('PUT')
            <input type="hidden" name="_modal" value="edit">

            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Employee <span class="text-rose-500">*</span></label>
                <select name="employee_id" required x-model="editingLeave.employee_id"
                    class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:ring-2 focus:ring-slate-500/60 outline-none">
                    @foreach ($employees as $employee)
                        <option value="{{ $employee->id }}">
                            {{ $employee->name }} ({{ $employee->employee_code }}) — {{ $employee->type === 'hourly' ? 'Hourly' : 'Daily' }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">Start date <span class="text-rose-500">*</span></label>
                    <input type="date" name="start_date" required x-model="editingLeave.start_date" @change="syncEndDate(editingLeave)"
                        class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:ring-2 focus:ring-slate-500/60 outline-none">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">End date</label>
                    <input type="date" name="end_date" x-model="editingLeave.end_date" :min="editingLeave.start_date"
                        class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:ring-2 focus:ring-slate-500/60 outline-none">
                </div>
            </div>

            <div x-show="isHourly(editingLeave.employee_id)" x-cloak>
                <label class="block text-xs font-medium text-slate-600 mb-1">Hours per leave day</label>
                <input type="number" name="hours_per_day" step="0.25" min="0" max="24" x-model="editingLeave.hours_per_day"
                    :placeholder="standardHours(editingLeave.employee_id)"
                    class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:ring-2 focus:ring-slate-500/60 outline-none">
                <p class="mt-1 text-[11px] text-slate-400">
                    Leave blank to use their standard day
                    (<span x-text="standardHours(editingLeave.employee_id)"></span> hrs).
                </p>
            </div>

            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Reason</label>
                <input type="text" name="reason" x-model="editingLeave.reason" maxlength="255"
                    class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:ring-2 focus:ring-slate-500/60 outline-none">
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
