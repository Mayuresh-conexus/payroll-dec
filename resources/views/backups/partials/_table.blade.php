{{-- backups/partials/_table.blade.php --}}
<div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
    <table class="min-w-full text-sm">
        <thead class="bg-slate-50 text-slate-500 uppercase text-xs font-semibold">
            <tr>
                <th class="px-4 py-3 text-left">Filename</th>
                <th class="px-4 py-3 text-center">Type</th>
                <th class="px-4 py-3 text-right">Size</th>
                <th class="px-4 py-3 text-center">Created</th>
                <th class="px-4 py-3 text-right">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            @forelse ($backups as $backup)
                <tr class="hover:bg-slate-50/80">
                    <td class="px-4 py-3 font-mono text-xs text-slate-700">{{ $backup['filename'] }}</td>
                    <td class="px-4 py-3 text-center">
                        @php
                            $typeClasses = match ($backup['type']) {
                                'manual' => 'bg-slate-100 text-slate-700',
                                'scheduled' => 'bg-sky-50 text-sky-700',
                                'prerestore' => 'bg-amber-50 text-amber-700',
                                default => 'bg-slate-100 text-slate-500',
                            };
                        @endphp
                        <span class="inline-flex px-2 py-1 rounded-full text-xs {{ $typeClasses }}">
                            {{ ucfirst($backup['type']) }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-right text-slate-600">{{ $backup['size_human'] }}</td>
                    <td class="px-4 py-3 text-center text-slate-500 text-xs" title="{{ $backup['created_at']->format('d M Y H:i:s') }}">
                        {{ $backup['created_at']->diffForHumans() }}
                    </td>
                    <td class="px-4 py-3 text-right">
                        <div class="flex justify-end items-center gap-2 text-slate-500">
                            {{-- Download --}}
                            <a href="{{ route('backups.download', $backup['filename']) }}"
                                data-tooltip="Download"
                                class="p-1.5 rounded-md hover:bg-brand-50 hover:text-brand-600 transition">
                                <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                                </svg>
                            </a>

                            {{-- Restore --}}
                            <button type="button" @click="openRestore(@json($backup))"
                                data-tooltip="Restore"
                                class="p-1.5 rounded-md hover:bg-amber-50 hover:text-amber-600 transition">
                                <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                                </svg>
                            </button>

                            {{-- Delete --}}
                            <button type="button" @click="openDelete(@json($backup))"
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
                    <td colspan="5" class="px-4 py-6 text-center text-sm text-slate-500">
                        No backups yet. Use "Backup Now" to create one.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
