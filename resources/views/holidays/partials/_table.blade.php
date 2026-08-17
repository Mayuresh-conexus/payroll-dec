{{-- holidays/partials/_table.blade.php — bank holiday list with edit/delete actions --}}
<div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
    <table class="min-w-full text-sm">
        <thead class="bg-slate-50 text-slate-500 uppercase text-xs font-semibold">
            <tr>
                <th class="px-4 py-3 text-left">Holiday</th>
                <th class="px-4 py-3 text-left">Dates</th>
                <th class="px-4 py-3 text-center">Days</th>
                <th class="px-4 py-3 text-left">Payroll weeks</th>
                <th class="px-4 py-3 text-left">Added by</th>
                <th class="px-4 py-3 text-right">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            @forelse ($holidays as $holiday)
                @php
                    $days = $holiday->start_date->diffInDays($holiday->end_date) + 1;
                    $runs = $affected[$holiday->id] ?? collect();
                    $isPast = $holiday->end_date->isPast();
                @endphp
                <tr class="hover:bg-slate-50/80 {{ $isPast ? 'text-slate-400' : '' }}">
                    <td class="px-4 py-3 font-medium {{ $isPast ? 'text-slate-500' : 'text-slate-800' }}">
                        {{ $holiday->name }}
                        @if (! $isPast && $holiday->start_date->isFuture())
                            <span class="ml-1 inline-flex px-2 py-0.5 rounded-full bg-sky-50 text-sky-700 text-[10px] font-semibold">Upcoming</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-slate-600">
                        {{ $holiday->start_date->format('d M Y') }}
                        @if ($days > 1)
                            <span class="text-slate-400">→</span> {{ $holiday->end_date->format('d M Y') }}
                        @endif
                    </td>
                    <td class="px-4 py-3 text-center text-slate-600">{{ $days }}</td>
                    <td class="px-4 py-3">
                        @forelse ($runs as $run)
                            <span class="inline-flex items-center gap-1 mr-1 px-2 py-0.5 rounded-full text-[10px] font-semibold
                                {{ $run->status === 'final' ? 'bg-rose-50 text-rose-700' : 'bg-amber-50 text-amber-700' }}"
                                title="{{ $run->status === 'final' ? 'Finalised — this holiday can no longer be changed' : 'Draft — refresh this week to apply changes' }}">
                                wk {{ $run->week_number }}
                                @if ($run->status === 'final') · locked @endif
                            </span>
                        @empty
                            <span class="text-xs text-slate-400">Not calculated yet</span>
                        @endforelse
                    </td>
                    <td class="px-4 py-3 text-slate-500 text-xs">{{ $holiday->creator?->name ?? '—' }}</td>
                    <td class="px-4 py-3 text-right">
                        <div class="flex justify-end items-center gap-2 text-slate-500">
                            <button type="button" @click="openEditModal(@js($holiday->only(['id', 'name', 'start_date', 'end_date'])))"
                                data-tooltip="Edit"
                                class="p-1.5 rounded-md hover:bg-brand-50 hover:text-brand-600 transition">
                                <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Z" />
                                </svg>
                            </button>

                            <button type="button" @click="openDeleteModal(@js($holiday->only(['id', 'name'])))"
                                data-tooltip="Delete"
                                class="p-1.5 rounded-md hover:bg-rose-50 hover:text-rose-600 transition">
                                <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 7h12M10 11v6m4-6v6M9 4h6v3H9zM4 7h16l-1 13H5L4 7Z" />
                                </svg>
                            </button>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="px-4 py-6 text-center text-sm text-slate-500">
                        No holidays yet. Use "Add Holiday" to create one.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
    <div class="px-4 py-3 border-t border-slate-100">{{ $holidays->links() }}</div>
</div>
