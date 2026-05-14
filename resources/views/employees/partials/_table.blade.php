{{-- _table.blade.php — Employees data table with edit/delete actions --}}
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
                            <span class="inline-flex px-2 py-1 rounded-full bg-emerald-50 text-emerald-700">Daily rate</span>
                        @else
                            <span class="inline-flex px-2 py-1 rounded-full bg-brand-50 text-brand-700">Hourly</span>
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
                    <td class="px-4 py-3 text-sm text-slate-600">{{ $employee->department ?? 'Not set' }}</td>
                    <td class="px-4 py-3 text-sm text-slate-600">
                        {{ $employee->joining_date ? $employee->joining_date->format('d M Y') : 'Not set' }}
                    </td>
                    <td class="px-4 py-3 text-center">
                        @if ($employee->is_active)
                            <span class="inline-flex px-2 py-1 rounded-full bg-emerald-50 text-emerald-700 text-xs">Active</span>
                        @else
                            <span class="inline-flex px-2 py-1 rounded-full bg-rose-50 text-rose-700 text-xs">Inactive</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-right">
                        <div class="flex justify-end items-center gap-2 text-slate-500">
                            {{-- Edit --}}
                            <button type="button"
                                @click='openEdit = true; editingEmployee = @json($employee); if (editingEmployee && editingEmployee.joining_date) { editingEmployee.joining_date = editingEmployee.joining_date.split("T")[0]; } fetchRates(editingEmployee.id)'
                                data-tooltip="Edit"
                                class="p-1.5 rounded-md hover:bg-brand-50 hover:text-brand-600 transition">
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
