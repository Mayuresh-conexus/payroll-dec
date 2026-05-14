{{-- payroll/partials/_history.blade.php — Payroll run activity log --}}
<div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">

    <div class="flex items-center justify-between px-5 py-3.5 border-b border-slate-100">
        <div class="flex items-center gap-2">
            <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
            </svg>
            <h3 class="text-sm font-semibold text-slate-800">Activity Log</h3>
            <span class="text-xs text-slate-400">— Week {{ $week }}/{{ $year }}</span>
        </div>
        @if ($run)
            <span class="text-xs text-slate-400">Run #{{ $run->id }}</span>
        @endif
    </div>

    <div class="divide-y divide-slate-50">

        {{-- Audit log entries (most recent first) --}}
        @forelse ($history as $entry)
            @php
                $isCreate    = $entry->action === 'created';
                $isFinalized = $entry->action === 'updated'
                    && isset($entry->new_values['status'])
                    && $entry->new_values['status'] === 'final';
                $isRefresh   = $entry->action === 'updated'
                    && isset($entry->new_values['generated_at'])
                    && !$isFinalized;

                [$iconBg, $iconColor, $icon, $label] = match(true) {
                    $isFinalized => ['bg-emerald-50 border-emerald-200', 'text-emerald-600',
                        '<path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>',
                        'Finalized'],
                    $isRefresh   => ['bg-amber-50 border-amber-200', 'text-amber-600',
                        '<path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99"/>',
                        'Recalculated from attendance'],
                    $isCreate    => ['bg-blue-50 border-blue-200', 'text-blue-600',
                        '<path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>',
                        'Payroll run created'],
                    default      => ['bg-slate-50 border-slate-200', 'text-slate-500',
                        '<path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125"/>',
                        'Payroll updated'],
                };
            @endphp

            <div class="flex items-start gap-3 px-5 py-3.5 hover:bg-slate-50/60 transition-colors">
                <div class="flex-shrink-0 mt-0.5 w-7 h-7 rounded-full border {{ $iconBg }} flex items-center justify-center">
                    <svg class="w-3.5 h-3.5 {{ $iconColor }}" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        {!! $icon !!}
                    </svg>
                </div>

                <div class="flex-1 min-w-0">
                    <p class="text-xs font-medium text-slate-800">{{ $label }}</p>
                    <p class="text-xs text-slate-400 mt-0.5 truncate">
                        by {{ $entry->user?->name ?? 'System' }}
                        @if (!empty($entry->new_values))
                            &middot;
                            @php
                                $changed = collect($entry->new_values)
                                    ->except(['updated_at', 'generated_at'])
                                    ->keys()
                                    ->map(fn($k) => str_replace('_', ' ', $k))
                                    ->implode(', ');
                            @endphp
                            @if ($changed)
                                <span class="text-slate-400">{{ $changed }} changed</span>
                            @endif
                        @endif
                    </p>
                </div>

                <div class="flex-shrink-0 text-right">
                    <span class="text-xs text-slate-400" title="{{ $entry->created_at->format('d M Y H:i:s') }}">
                        {{ $entry->created_at->diffForHumans() }}
                    </span>
                </div>
            </div>
        @empty
            {{-- Fallback: show run metadata if no audit entries yet --}}
            @if ($run)
                <div class="flex items-start gap-3 px-5 py-3.5">
                    <div class="flex-shrink-0 mt-0.5 w-7 h-7 rounded-full border bg-blue-50 border-blue-200 flex items-center justify-center">
                        <svg class="w-3.5 h-3.5 text-blue-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
                        </svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-xs font-medium text-slate-800">Payroll run created</p>
                        <p class="text-xs text-slate-400 mt-0.5">Week {{ $week }}/{{ $year }}</p>
                    </div>
                    <span class="flex-shrink-0 text-xs text-slate-400" title="{{ $run->created_at }}">
                        {{ \Carbon\Carbon::parse($run->created_at)->diffForHumans() }}
                    </span>
                </div>

                @if ($run->status === 'final')
                    <div class="flex items-start gap-3 px-5 py-3.5">
                        <div class="flex-shrink-0 mt-0.5 w-7 h-7 rounded-full border bg-emerald-50 border-emerald-200 flex items-center justify-center">
                            <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
                            </svg>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-xs font-medium text-slate-800">Finalized</p>
                            <p class="text-xs text-slate-400 mt-0.5">Payroll locked</p>
                        </div>
                        <span class="flex-shrink-0 text-xs text-slate-400" title="{{ $run->updated_at }}">
                            {{ \Carbon\Carbon::parse($run->updated_at)->diffForHumans() }}
                        </span>
                    </div>
                @endif
            @else
                <div class="px-5 py-8 text-center text-xs text-slate-400">
                    No payroll run for this week yet. Save payroll to start tracking activity.
                </div>
            @endif
        @endforelse

    </div>

</div>
