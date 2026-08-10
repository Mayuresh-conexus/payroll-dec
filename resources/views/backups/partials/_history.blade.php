{{-- backups/partials/_history.blade.php — Backup activity log --}}
<div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">

    <div class="flex items-center justify-between px-5 py-3.5 border-b border-slate-100">
        <div class="flex items-center gap-2">
            <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
            </svg>
            <h3 class="text-sm font-semibold text-slate-800">Activity Log</h3>
        </div>
    </div>

    <div class="divide-y divide-slate-50">
        @forelse ($history as $entry)
            @php
                $nv = $entry->new_values ?? [];

                [$dotColor, $iconColor, $iconPath, $label] = match ($entry->action) {
                    'created' => [
                        'bg-brand-500', 'text-brand-600',
                        '<path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>',
                        'Backup created',
                    ],
                    'restored' => [
                        'bg-amber-500', 'text-amber-600',
                        '<path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99"/>',
                        'Database restored',
                    ],
                    'deleted' => [
                        'bg-rose-500', 'text-rose-600',
                        '<path stroke-linecap="round" stroke-linejoin="round" d="M6 7h12M10 11v6m4-6v6M9 4h6v3H9zM4 7h16l-1 13H5L4 7Z"/>',
                        'Backup deleted',
                    ],
                    'downloaded' => [
                        'bg-slate-400', 'text-slate-500',
                        '<path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3"/>',
                        'Backup downloaded',
                    ],
                    default => [
                        'bg-slate-300', 'text-slate-500',
                        '<path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Z"/>',
                        'Schedule updated',
                    ],
                };

                $summary = match ($entry->action) {
                    'created' => ($nv['filename'] ?? '—').' ('.($nv['reason'] ?? 'manual').')',
                    'restored' => 'from '.($nv['restored_from'] ?? '—').' — safety backup: '.($nv['pre_restore_filename'] ?? '—'),
                    'deleted', 'downloaded' => $nv['filename'] ?? '—',
                    default => null,
                };
            @endphp

            <div class="px-5 py-3.5 hover:bg-slate-50/60 transition-colors flex items-center gap-3">
                <div class="flex-shrink-0 w-7 h-7 rounded-full {{ $dotColor }} bg-opacity-15 border border-current/10 flex items-center justify-center">
                    <svg class="w-3.5 h-3.5 {{ $iconColor }}" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        {!! $iconPath !!}
                    </svg>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="text-xs font-semibold text-slate-800">{{ $label }}</span>
                        @if ($summary)
                            <span class="text-xs text-slate-400 font-mono truncate">{{ $summary }}</span>
                        @endif
                    </div>
                    <p class="text-xs text-slate-400 mt-0.5">
                        by {{ $entry->user?->name ?? 'System' }}
                        · <span title="{{ $entry->created_at->format('d M Y H:i:s') }}">{{ $entry->created_at->diffForHumans() }}</span>
                    </p>
                </div>
            </div>
        @empty
            <div class="px-5 py-8 text-center text-xs text-slate-400">
                No backup activity yet.
            </div>
        @endforelse
    </div>

</div>
