@extends('layouts.app')

@section('title', 'Employees')
@section('page_title', 'Employees')

@section('content')

    <script>
        function employeesData() {
            return {
                openCreate: false,
                openEdit: false,
                editingEmployee: {},
                baseUpdateUrl: @json(url('employees')),
                async fetchRates(id) {
                    try {
                        const res = await fetch(this.baseUpdateUrl + '/' + id + '/rates', {
                            headers: {
                                'Accept': 'application/json'
                            }
                        });
                        if (!res.ok) return;
                        const json = await res.json();
                        const all = json.data || [];
                        this.editingEmployee = this.editingEmployee || {};
                        this.editingEmployee.rates = all;

                        // group by type and compute prev amount for diff view
                        const types = ['daily_rate', 'hourly_rate', 'hours_per_day'];
                        this.editingEmployee.ratesByType = {};
                        this.editingEmployee.rateVisible = this.editingEmployee.rateVisible || {};
                        types.forEach(t => {
                            let list = all.filter(r => r.rate_type === t)
                                .sort((a, b) => new Date(b.effective_from || b.created_at) - new Date(a
                                    .effective_from || a.created_at));
                            list = list.map((r, i) => Object.assign({}, r, {
                                prev_amount: (list[i + 1] ? list[i + 1].amount : null)
                            }));
                            this.editingEmployee.ratesByType[t] = list;
                            if (!this.editingEmployee.rateVisible[t]) this.editingEmployee.rateVisible[t] = 5;
                        });
                    } catch (e) {
                        console.error('Failed to load rates', e);
                    }
                },
                toggleMore(type) {
                    if (!this.editingEmployee) this.editingEmployee = {};
                    if (!this.editingEmployee.rateVisible) this.editingEmployee.rateVisible = {};
                    if (!this.editingEmployee.ratesByType) this.editingEmployee.ratesByType = {};
                    const total = (this.editingEmployee.ratesByType[type] || []).length;
                    // Only expand to show all; do not provide 'show less' collapse
                    this.editingEmployee.rateVisible[type] = total;
                }
            }
        }
    </script>

    <div x-data="employeesData()" class="space-y-6">

        {{-- Header actions --}}
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-xl font-semibold text-slate-800">Employees</h1>
                <p class="text-sm text-slate-500">Manage daily rate and hourly employees in one place.</p>
            </div>

            <button @click="openCreate = true"
                class="inline-flex items-center px-4 py-2 rounded-lg text-sm font-medium bg-slate-900 text-white hover:bg-slate-800">
                + Add Employee
            </button>
        </div>

        {{-- Table card --}}
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-slate-500 uppercase text-xs font-semibold">
                    <tr>
                        <th class="px-4 py-3 text-left">Code</th>
                        <th class="px-4 py-3 text-left">Name</th>
                        <th class="px-4 py-3 text-left">Type</th>
                        <th class="px-4 py-3 text-left">Rate</th>
                        <th class="px-4 py-3 text-left">Department</th>
                        <th class="px-4 py-3 text-left">Joining</th>
                        <th class="px-4 py-3 text-center">Status</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($employees as $employee)
                        <tr class="hover:bg-slate-50/80">
                            <td class="px-4 py-3 font-mono text-xs text-slate-600">
                                {{ $employee->employee_code }}
                            </td>
                            <td class="px-4 py-3 text-sm font-medium text-slate-800">
                                {{ $employee->name }}
                            </td>
                            <td class="px-4 py-3 text-xs">
                                @if ($employee->type === 'daily_rate')
                                    <span class="inline-flex px-2 py-1 rounded-full bg-emerald-50 text-emerald-700">
                                        Daily rate
                                    </span>
                                @else
                                    <span class="inline-flex px-2 py-1 rounded-full bg-blue-50 text-blue-700">
                                        Hourly
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm text-slate-700">
                                @if ($employee->type === 'daily_rate')
                                    {{ number_format($employee->daily_rate, 2) }} / day
                                @else
                                    {{ number_format($employee->hourly_rate, 2) }} / hour
                                    @if ($employee->hours_per_day)
                                        · {{ rtrim(rtrim(number_format($employee->hours_per_day, 2), '0'), '.') }} hrs/day
                                    @endif
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm text-slate-600">
                                {{ $employee->department ?? 'Not set' }}
                            </td>
                            <td class="px-4 py-3 text-sm text-slate-600">
                                {{ $employee->joining_date ? $employee->joining_date->format('d M Y') : 'Not set' }}
                            </td>
                            <td class="px-4 py-3 text-center">
                                @if ($employee->is_active)
                                    <span class="inline-flex px-2 py-1 rounded-full bg-emerald-50 text-emerald-700 text-xs">
                                        Active
                                    </span>
                                @else
                                    <span class="inline-flex px-2 py-1 rounded-full bg-rose-50 text-rose-700 text-xs">
                                        Inactive
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex justify-end items-center gap-2 text-slate-500">
                                    {{-- Edit --}}
                                    <button type="button"
                                        @click='openEdit = true; editingEmployee = @json($employee); if (editingEmployee && editingEmployee.joining_date) { editingEmployee.joining_date = editingEmployee.joining_date.split("T")[0]; } fetchRates(editingEmployee.id)'
                                        data-tooltip="Edit"
                                        class="p-1.5 rounded-md hover:bg-blue-50 hover:text-blue-600 transition">
                                        <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none"
                                            viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M16.862 3.487a1.5 1.5 0 0 1 2.121 0l1.53 1.53a1.5 1.5 0 0 1 0 2.122l-10.01 10.01-4.243.707.707-4.243 10-10.126Z" />
                                        </svg>
                                    </button>

                                    {{-- Delete --}}
                                    <form action="{{ route('employees.destroy', $employee->id) }}" method="POST"
                                        class="inline" onsubmit="return confirm('Delete this employee?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" data-tooltip="Delete"
                                            class="p-1.5 rounded-md hover:bg-rose-50 hover:text-rose-600 transition">
                                            <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none"
                                                viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                    d="M6 7h12M10 11v6m4-6v6M9 4h6v3H9zM4 7h16l-1 13H5L4 7Z" />
                                            </svg>
                                        </button>
                                    </form>

                                </div>
                            </td>

                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-6 text-center text-sm text-slate-500">
                                No employees found. Use "Add Employee" to create one.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            {{-- Pagination --}}
            <div class="px-4 py-3 border-t border-slate-100">
                {{ $employees->links() }}
            </div>
        </div>

        {{-- Create employee modal --}}
        <div x-show="openCreate" x-cloak x-transition.scale style="margin-top: 0"
            class="fixed inset-0 z-40 flex items-center justify-center bg-black/40">
            <div @click.away="openCreate = false"
                class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl p-6 sm:p-7 space-y-6" x-data="{ empType: 'daily_rate' }">

                {{-- Header --}}
                <div class="flex items-start justify-between gap-4 px-6 sm:px-7 py-6">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-900">Add employee</h2>
                        <p class="mt-1 text-xs text-slate-500">
                            Create a new team member and set their pay type and rate.
                        </p>
                    </div>
                    <button type="button"
                        class="inline-flex items-center justify-center rounded-full w-8 h-8 text-slate-400 hover:text-slate-600 hover:bg-slate-100"
                        @click="openCreate = false">
                        ✕
                    </button>
                </div>

                {{-- Form --}}
                <form action="{{ route('employees.store') }}" method="post" class="space-y-5">
                    @csrf

                    {{-- Basic details --}}
                    <div class="space-y-3">
                        <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                            Basic details
                        </h3>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-medium text-slate-600 mb-1">
                                    Employee code <span class="text-rose-500">*</span>
                                </label>
                                <input type="text" name="employee_code" required
                                    class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:ring-2 focus:ring-slate-500/60 focus:border-slate-500 outline-none"
                                    placeholder="E001, HR-12, etc">
                            </div>

                            <div>
                                <label class="block text-xs font-medium text-slate-600 mb-1">
                                    Name <span class="text-rose-500">*</span>
                                </label>
                                <input type="text" name="name" required
                                    class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:ring-2 focus:ring-slate-500/60 focus:border-slate-500 outline-none"
                                    placeholder="Full name">
                            </div>

                            <div>
                                <label class="block text-xs font-medium text-slate-600 mb-1">
                                    Joining date
                                </label>
                                <input type="date" name="joining_date"
                                    class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:ring-2 focus:ring-slate-500/60 focus:border-slate-500 outline-none">
                            </div>

                            <div>
                                <label class="block text-xs font-medium text-slate-600 mb-1">
                                    Department
                                </label>
                                <input type="text" name="department"
                                    class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:ring-2 focus:ring-slate-500/60 focus:border-slate-500 outline-none"
                                    placeholder="Accounts, HR, Production">
                            </div>
                        </div>
                    </div>

                    <hr class="border-slate-100">

                    {{-- Type and rate --}}
                    <div class="space-y-3">
                        <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                            Pay type and rate
                        </h3>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 items-start">
                            <div class="sm:col-span-1">
                                <label class="block text-xs font-medium text-slate-600 mb-1">
                                    Type <span class="text-rose-500">*</span>
                                </label>
                                <select name="type" x-model="empType"
                                    class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:ring-2 focus:ring-slate-500/60 focus:border-slate-500 outline-none bg-white">
                                    <option value="daily_rate">Daily rate</option>
                                    <option value="hourly">Hourly</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-xs font-medium text-slate-600 mb-1">
                                    Daily rate
                                </label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-3 flex items-center text-xs text-slate-400">
                                        €
                                    </span>
                                    <input type="number" step="0.01" name="daily_rate"
                                        x-bind:disabled="empType !== 'daily_rate'"
                                        class="w-full rounded-lg border border-slate-200 pl-7 pr-3 py-2 text-sm focus:ring-2 focus:ring-slate-500/60 focus:border-slate-500 outline-none"
                                        placeholder="For staff">
                                </div>
                                <p class="mt-1 text-[11px] text-slate-400" x-show="empType !== 'daily_rate'">
                                    Enabled only when type is Daily rate.
                                </p>
                            </div>

                            <div>
                                <label class="block text-xs font-medium text-slate-600 mb-1">
                                    Hourly rate
                                </label>
                                <div class="relative" x-show="empType === 'hourly'">
                                    <span class="absolute inset-y-0 left-3 flex items-center text-xs text-slate-400">
                                        €
                                    </span>
                                    <input type="number" step="0.01" name="hourly_rate"
                                        x-bind:disabled="empType !== 'hourly'"
                                        class="w-full rounded-lg border border-slate-200 pl-7 pr-3 py-2 text-sm focus:ring-2 focus:ring-slate-500/60 focus:border-slate-500 outline-none"
                                        placeholder="For hourly staff">
                                </div>
                                <p class="mt-1 text-[11px] text-slate-400" x-show="empType !== 'hourly'">
                                    Enabled only when type is Hourly.
                                </p>
                            </div>

                            <div>
                                <label class="block text-xs font-medium text-slate-600 mb-1">
                                    Hours per day
                                </label>
                                <input type="number" name="hours_per_day" step="0.25" min="0"
                                    x-bind:disabled="empType !== 'hourly'"
                                    class="w-full border rounded px-3 py-2 text-sm" x-show="empType === 'hourly'">
                                <p class="mt-1 text-[11px] text-slate-400" x-show="empType !== 'hourly'">
                                    Only used for hourly employees.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">
                            Rate effective from
                        </label>
                        <input type="date" name="rate_effective_from" value="{{ now()->toDateString() }}"
                            class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                        <p class="mt-1 text-[11px] text-slate-400">Choose the date when these rates become effective.</p>
                    </div>

                    <hr class="border-slate-100">

                    {{-- Footer buttons --}}
                    <div class="flex flex-col sm:flex-row justify-end gap-3 pt-1">
                        <button type="button" @click="openCreate = false"
                            class="inline-flex justify-center px-4 py-2 rounded-lg border border-slate-200 text-sm font-medium text-slate-700 hover:bg-slate-50">
                            Cancel
                        </button>
                        <button type="submit"
                            class="inline-flex justify-center px-4 py-2 rounded-lg bg-slate-900 text-sm font-semibold text-white hover:bg-slate-800">
                            Save employee
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Edit employee modal --}}
        <div style="margin-top: 0" x-show="openEdit && editingEmployee" x-cloak
            class="fixed inset-0 z-40 flex items-center justify-center bg-black/40">
            <div @click.away="openEdit = false"
                class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-hidden flex flex-col">
                {{-- Header --}}
                <div class="flex items-start pr-6 pl-6 pt-6 justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-900">
                            Edit employee
                        </h2>
                        <p class="mt-1 text-xs text-slate-500">
                            Update details for this employee.
                        </p>
                    </div>
                    <button type="button"
                        class="inline-flex items-center justify-center rounded-full w-8 h-8 text-slate-400 hover:text-slate-600 hover:bg-slate-100"
                        @click="openEdit = false">
                        ✕
                    </button>
                </div>

                {{-- Form --}}
                <form method="POST" :action="baseUpdateUrl + '/' + (editingEmployee ? editingEmployee.id : '')"
                    class="space-y-5 flex-1 flex flex-col min-h-0">
                    @csrf
                    @method('PUT')

                    <div class="overflow-auto flex-1 pr-6 pl-6 pb-6 min-h-0">

                        {{-- Basic details --}}
                        <div class="space-y-3 pb-3">
                            <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                                Basic details
                            </h3>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-medium text-slate-600 mb-1">
                                        Employee code <span class="text-rose-500">*</span>
                                    </label>
                                    <input type="text" name="employee_code" required
                                        class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:ring-2 focus:ring-slate-500/60 focus:border-slate-500 outline-none"
                                        x-model="editingEmployee.employee_code">
                                </div>

                                <div>
                                    <label class="block text-xs font-medium text-slate-600 mb-1">
                                        Name <span class="text-rose-500">*</span>
                                    </label>
                                    <input type="text" name="name" required
                                        class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:ring-2 focus:ring-slate-500/60 focus:border-slate-500 outline-none"
                                        x-model="editingEmployee.name">
                                </div>

                                <div>
                                    <label class="block text-xs font-medium text-slate-600 mb-1">
                                        Joining date
                                    </label>
                                    <input type="date" name="joining_date"
                                        class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:ring-2 focus:ring-slate-500/60 focus:border-slate-500 outline-none"
                                        x-model="editingEmployee.joining_date">
                                </div>

                                <div>
                                    <label class="block text-xs font-medium text-slate-600 mb-1">
                                        Department
                                    </label>
                                    <input type="text" name="department"
                                        class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:ring-2 focus:ring-slate-500/60 focus:border-slate-500 outline-none"
                                        x-model="editingEmployee.department">
                                </div>
                            </div>
                        </div>

                        <hr class="border-slate-100 pb-3">

                        {{-- Type and rate --}}
                        <div class="space-y-3">
                            <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                                Pay type and rate
                            </h3>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 items-start">
                                <div class="sm:col-span-1">
                                    <label class="block text-xs font-medium text-slate-600 mb-1">
                                        Type <span class="text-rose-500">*</span>
                                    </label>

                                    <!-- When editing, lock the type and submit it as a hidden field -->
                                    <div x-show="editingEmployee" class="flex flex-col">
                                        <input type="text"
                                            :value="(editingEmployee.type === 'daily_rate' ? 'Daily rate' : 'Hourly')"
                                            disabled
                                            class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm bg-slate-50 text-slate-700">
                                        <input type="hidden" name="type" :value="editingEmployee.type">
                                    </div>

                                    <!-- Fallback (shouldn't normally show) -->
                                    <select name="type" x-show="!editingEmployee" x-model="editingEmployee.type"
                                        class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:ring-2 focus:ring-slate-500/60 focus:border-slate-500 outline-none bg-white">
                                        <option value="daily_rate">Daily rate</option>
                                        <option value="hourly">Hourly</option>
                                    </select>
                                </div>

                                <template x-if="editingEmployee && editingEmployee.type === 'daily_rate'">
                                    <div>
                                        <label class="block text-xs font-medium text-slate-600 mb-1">Daily rate</label>
                                        <div class="relative">
                                            <span
                                                class="absolute inset-y-0 left-3 flex items-center text-xs text-slate-400">€</span>
                                            <input type="number" step="0.01" name="daily_rate"
                                                x-model="editingEmployee.daily_rate"
                                                class="w-full rounded-lg border border-slate-200 pl-7 pr-3 py-2 text-sm focus:ring-2 focus:ring-slate-500/60 focus:border-slate-500 outline-none">
                                        </div>
                                    </div>
                                </template>

                                <template x-if="editingEmployee && editingEmployee.type === 'hourly'">
                                    <div>
                                        <label class="block text-xs font-medium text-slate-600 mb-1">Hourly rate</label>
                                        <div class="relative">
                                            <span
                                                class="absolute inset-y-0 left-3 flex items-center text-xs text-slate-400">€</span>
                                            <input type="number" step="0.01" name="hourly_rate"
                                                x-model="editingEmployee.hourly_rate"
                                                class="w-full rounded-lg border border-slate-200 pl-7 pr-3 py-2 text-sm focus:ring-2 focus:ring-slate-500/60 focus:border-slate-500 outline-none">
                                        </div>

                                        <div class="mt-2">
                                            <label class="block text-xs font-medium text-slate-600 mb-1">Hours per
                                                day</label>
                                            <input type="number" name="hours_per_day" step="0.25" min="0"
                                                x-model="editingEmployee.hours_per_day"
                                                class="w-full border rounded px-3 py-2 text-sm">
                                        </div>
                                    </div>
                                </template>

                                <template
                                    x-if="editingEmployee && !['daily_rate','hourly'].includes(editingEmployee.type)">
                                    <div>
                                        <label class="block text-xs font-medium text-slate-600 mb-1">Daily rate</label>
                                        <input type="number" step="0.01" name="daily_rate"
                                            x-model="editingEmployee.daily_rate"
                                            class="w-full rounded-lg border border-slate-200 pl-7 pr-3 py-2 text-sm">
                                        <label class="block text-xs font-medium text-slate-600 mb-1 mt-2">Hourly
                                            rate</label>
                                        <input type="number" step="0.01" name="hourly_rate"
                                            x-model="editingEmployee.hourly_rate"
                                            class="w-full rounded-lg border border-slate-200 pl-7 pr-3 py-2 text-sm">
                                    </div>
                                </template>
                            </div>
                        </div>

                        <div class="pb-3">
                            <label class="block text-xs font-medium text-slate-600 mb-1">
                                Rate effective from
                            </label>
                            <input type="date" name="rate_effective_from"
                                x-model="editingEmployee.rate_effective_from"
                                class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                            <p class="mt-1 text-[11px] text-slate-400">When updating rates, this date controls the
                                effective-from for the recorded rate change.</p>
                        </div>

                        <hr class="border-slate-100 pb-3">

                        {{-- Rate history --}}
                        <div class="space-y-3 pb-3">
                            <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-500">Rate history</h3>
                            <div class="text-sm text-slate-700">
                                <template x-if="editingEmployee && editingEmployee.ratesByType">
                                    <div class="space-y-4">
                                        <template x-for="rateType in ['daily_rate','hourly_rate','hours_per_day']"
                                            :key="rateType">
                                            <div>
                                                <div class="flex items-center justify-between">
                                                    <h4 class="text-xs font-medium text-slate-600"
                                                        x-text="(rateType === 'daily_rate' ? 'Daily rates' : (rateType === 'hourly_rate' ? 'Hourly rates' : 'Hours per day'))">
                                                    </h4>
                                                    <button type="button" class="text-xs text-slate-500 underline"
                                                        @click="toggleMore(rateType)"
                                                        x-show="(editingEmployee.ratesByType[rateType] || []).length && ((editingEmployee.rateVisible && editingEmployee.rateVisible[rateType]) < (editingEmployee.ratesByType[rateType] || []).length)">
                                                        <span x-text="'Show all'"></span>
                                                    </button>
                                                </div>
                                                <div class="mt-2 bg-slate-50 rounded border border-slate-100 p-3">
                                                    <template x-if="(editingEmployee.ratesByType[rateType] || []).length">
                                                        <table class="w-full text-xs">
                                                            <thead>
                                                                <tr class="text-slate-500 text-left">
                                                                    <th class="py-1">Change</th>
                                                                    <th class="py-1">Effective</th>
                                                                    <th class="py-1">By</th>
                                                                    <th class="py-1">When</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                <template
                                                                    x-for="r in (editingEmployee.ratesByType[rateType] || []).slice(0, (editingEmployee.rateVisible && editingEmployee.rateVisible[rateType]) || 5)"
                                                                    :key="r.id">
                                                                    <tr>
                                                                        <td class="py-1">
                                                                            <span
                                                                                x-text="(r.prev_amount !== null ? Number(r.prev_amount).toFixed(2) + ' → ' : '') + (r.amount !== null ? Number(r.amount).toFixed(2) : '-')"></span>
                                                                            <span class="text-slate-400"
                                                                                x-text="rateType === 'daily_rate' ? ' /day' : (rateType === 'hourly_rate' ? ' /hr' : '')"></span>
                                                                        </td>
                                                                        <td class="py-1"
                                                                            x-text="r.effective_from ?? '-'">
                                                                        </td>
                                                                        <td class="py-1"
                                                                            x-text="r.created_by_name ?? r.created_by ?? '-'">
                                                                        </td>
                                                                        <td class="py-1" x-text="r.created_at ?? '-'">
                                                                        </td>
                                                                    </tr>
                                                                </template>
                                                            </tbody>
                                                        </table>
                                                    </template>
                                                    <template x-if="!(editingEmployee.ratesByType[rateType] || []).length">
                                                        <div class="text-xs text-slate-400">No entries.</div>
                                                    </template>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </template>
                                <template x-if="!editingEmployee || !editingEmployee.ratesByType">
                                    <div class="text-xs text-slate-400">No rate history available.</div>
                                </template>
                            </div>
                        </div>

                        {{-- Active status --}}
                        <input type="hidden" name="is_active" value="0">

                        <label class="inline-flex items-center gap-2 text-xs text-slate-600">
                            <input type="checkbox" name="is_active" value="1"
                                x-bind:checked="editingEmployee && editingEmployee.is_active"
                                class="rounded border-slate-300 text-slate-700 focus:ring-slate-500">
                            <span>Employee is active</span>
                        </label>





                    </div>

                    {{-- Footer buttons --}}
                    <div
                        class="flex-shrink-0 border-t border-slate-100 p-6 sm:p-7 bg-white flex flex-col sm:flex-row justify-end gap-3">
                        <button type="button" @click="openEdit = false"
                            class="inline-flex justify-center px-4 py-2 rounded-lg border border-slate-200 text-sm font-medium text-slate-700 hover:bg-slate-50">
                            Cancel
                        </button>
                        <button type="submit"
                            class="inline-flex justify-center px-4 py-2 rounded-lg bg-slate-900 text-sm font-semibold text-white hover:bg-slate-800">
                            Update employee
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Optional old helper, no longer required for the modals, can be removed --}}
        <script>
            function updateRateFields(e) {
                const type = e.target.value;
                const daily = document.querySelector('input[name="daily_rate"]');
                const hourly = document.querySelector('input[name="hourly_rate"]');

                if (!daily || !hourly) return;

                if (type === 'daily_rate') {
                    daily.removeAttribute('disabled');
                    hourly.setAttribute('disabled', 'disabled');
                    hourly.value = '';
                } else {
                    hourly.removeAttribute('disabled');
                    daily.setAttribute('disabled', 'disabled');
                    daily.value = '';
                }
            }
        </script>
    </div>
@endsection
