{{-- leaves/partials/_create_modal.blade.php --}}
<div x-show="openCreate" x-cloak x-transition.opacity style="margin-top:0"
    class="fixed inset-0 z-40 flex items-center justify-center bg-black/40">
    <div @click.away="openCreate = false"
        class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-6 space-y-5"
        x-data="{ form: {
            employee_id: '{{ old('employee_id') }}',
            start_date: '{{ old('start_date') }}',
            end_date: '{{ old('end_date') }}',
        } }">

        <div class="flex items-start justify-between">
            <div>
                <h2 class="text-lg font-semibold text-slate-900">Record leave</h2>
                <p class="mt-1 text-xs text-slate-500">Paid at the employee's own rate and deducted from their balance.</p>
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

        <form action="{{ route('leaves.store') }}" method="POST" class="space-y-4">
            @csrf
            <input type="hidden" name="_modal" value="create">

            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Employee <span class="text-rose-500">*</span></label>
                <select name="employee_id" required x-model="form.employee_id"
                    class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:ring-2 focus:ring-slate-500/60 outline-none">
                    <option value="">Choose an employee…</option>
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
                    <input type="date" name="start_date" required x-model="form.start_date" @change="syncEndDate(form)"
                        class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:ring-2 focus:ring-slate-500/60 outline-none">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">End date</label>
                    <input type="date" name="end_date" x-model="form.end_date" :min="form.start_date"
                        class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:ring-2 focus:ring-slate-500/60 outline-none">
                </div>
            </div>

            {{-- Only hourly staff are paid leave by the hour; daily staff take whole days. --}}
            <div x-show="isHourly(form.employee_id)" x-cloak>
                <label class="block text-xs font-medium text-slate-600 mb-1">Hours per leave day</label>
                <input type="number" name="hours_per_day" step="0.25" min="0" max="24" value="{{ old('hours_per_day') }}"
                    :placeholder="standardHours(form.employee_id)"
                    class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:ring-2 focus:ring-slate-500/60 outline-none">
                <p class="mt-1 text-[11px] text-slate-400">
                    Leave blank to use their standard day
                    (<span x-text="standardHours(form.employee_id)"></span> hrs). Every day of the range is paid this many hours.
                </p>
            </div>

            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Reason</label>
                <input type="text" name="reason" value="{{ old('reason') }}" maxlength="255"
                    class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:ring-2 focus:ring-slate-500/60 outline-none"
                    placeholder="Annual leave">
            </div>

            <p class="text-[11px] text-slate-400">
                Pick a start date for a single day, or extend the end date for a longer break. Days outside the
                employee's working week are not deducted or paid, and a day already marked present in attendance is
                never paid twice.
            </p>

            <div class="flex justify-end gap-3 pt-1">
                <button type="button" @click="openCreate = false"
                    class="px-4 py-2 rounded-lg border border-slate-200 text-sm font-medium text-slate-700 hover:bg-slate-50 transition">
                    Cancel
                </button>
                <button type="submit"
                    class="px-4 py-2 rounded-lg bg-slate-900 text-white text-sm font-medium hover:bg-slate-800 transition">
                    Record leave
                </button>
            </div>
        </form>
    </div>
</div>
