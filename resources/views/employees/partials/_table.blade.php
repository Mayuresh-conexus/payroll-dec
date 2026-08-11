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
                            @php $latestDaily = $employee->latestRateOf('daily_rate'); @endphp
                            <div class="flex items-center gap-1.5">
                                <span class="font-mono font-medium">€{{ number_format($latestDaily, 2) }}</span>
                                <span class="text-slate-400 text-xs">/ day</span>
                                @if ($latestDaily != (float) $employee->daily_rate)
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold bg-amber-50 text-amber-700 border border-amber-200"
                                          title="Rate was updated from €{{ number_format($employee->daily_rate, 2) }}">
                                        updated
                                    </span>
                                @endif
                            </div>
                        @else
                            @php
                                $latestHourly  = $employee->latestRateOf('hourly_rate');
                                $latestHpd     = $employee->latestRateOf('hours_per_day');
                            @endphp
                            <div class="flex items-center gap-1.5">
                                <span class="font-mono font-medium">€{{ number_format($latestHourly, 2) }}</span>
                                <span class="text-slate-400 text-xs">/ hr</span>
                                @if ($latestHourly != (float) $employee->hourly_rate)
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold bg-amber-50 text-amber-700 border border-amber-200"
                                          title="Rate was updated from €{{ number_format($employee->hourly_rate, 2) }}">
                                        updated
                                    </span>
                                @endif
                            </div>
                            @if ($latestHpd)
                                <div class="text-xs text-slate-400 mt-0.5">
                                    {{ rtrim(rtrim(number_format($latestHpd, 2), '0'), '.') }} hrs/day
                                </div>
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
                            {{-- View profile --}}
                            <a href="{{ route('employees.show', $employee->id) }}"
                                data-tooltip="View profile"
                                class="p-1.5 rounded-md hover:bg-emerald-50 hover:text-emerald-600 transition">
                                <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none"
                                    viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                </svg>
                            </a>

                            {{-- Edit --}}
                            <a href="{{ route('employees.edit', $employee) }}"
                                data-tooltip="Edit"
                                class="p-1.5 rounded-md hover:bg-brand-50 hover:text-brand-600 transition">
                                <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none"
                                    viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M16.862 3.487a1.5 1.5 0 0 1 2.121 0l1.53 1.53a1.5 1.5 0 0 1 0 2.122l-10.01 10.01-4.243.707.707-4.243 10-10.126Z" />
                                </svg>
                            </a>

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
