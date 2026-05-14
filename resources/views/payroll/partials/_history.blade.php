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

    @php
        // Filter out auto-generated "only timestamp changed" entries — keep only meaningful ones
        $meaningful = $history->filter(function ($e) {
            $nv = $e->new_values ?? [];
            // Keep: rich payroll entries, finalization, created events
            if (isset($nv['action_type'])) return true;
            if ($e->action === 'created') return true;
            if (isset($nv['status'])) return true;
            // Skip pure timestamp-only updates (generated_at / updated_at / period_type)
            $keys = array_keys($nv);
            return count(array_diff($keys, ['generated_at', 'updated_at', 'period_type', 'created_by'])) > 0;
        });
    @endphp

    <div class="divide-y divide-slate-50">

        @forelse ($meaningful as $entry)
            @php
                $nv         = $entry->new_values ?? [];
                $actionType = $nv['action_type'] ?? null;
                $employees  = $nv['employees']   ?? [];

                $isSave        = $actionType === 'save';
                $isRecalc      = $actionType === 'recalculate';
                $isFinalized   = !$actionType && ($nv['status'] ?? null) === 'final';
                $isCreated     = $entry->action === 'created';

                [$dotColor, $iconPath, $label] = match(true) {
                    $isFinalized => [
                        'bg-emerald-500',
                        '<path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>',
                        'Finalized',
                    ],
                    $isRecalc    => [
                        'bg-amber-500',
                        '<path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99"/>',
                        'Recalculated from attendance',
                    ],
                    $isSave      => [
                        'bg-brand-500',
                        '<path stroke-linecap="round" stroke-linejoin="round" d="M9 3.75H6.912a2.25 2.25 0 0 0-2.15 1.588L2.35 13.177a2.25 2.25 0 0 0-.1.661V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18v-4.162c0-.224-.034-.447-.1-.661L19.24 5.338a2.25 2.25 0 0 0-2.15-1.588H15M2.25 13.5h3.86a2.25 2.25 0 0 1 2.012 1.244l.256.512a2.25 2.25 0 0 0 2.013 1.244h3.218a2.25 2.25 0 0 0 2.013-1.244l.256-.512a2.25 2.25 0 0 1 2.013-1.244h3.859M12 3v8.25m0 0-3-3m3 3 3-3"/>',
                        'Payroll saved',
                    ],
                    $isCreated   => [
                        'bg-slate-400',
                        '<path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>',
                        'Payroll run created',
                    ],
                    default      => [
                        'bg-slate-300',
                        '<path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Z"/>',
                        'Updated',
                    ],
                };

                // Separate employees that had actual changes vs no changes
                $changed    = collect($employees)->filter(fn($e) => count(array_intersect(array_keys($e), ['weekly','cash','bank'])) > 0);
                $unchanged  = collect($employees)->filter(fn($e) => count(array_intersect(array_keys($e), ['weekly','cash','bank'])) === 0);
            @endphp

            <div x-data="{ expanded: {{ ($isSave || $isRecalc) && $changed->count() > 0 ? 'true' : 'false' }} }"
                 class="px-5 py-3.5 hover:bg-slate-50/60 transition-colors">

                {{-- Header row --}}
                <div class="flex items-center gap-3">
                    {{-- Dot icon --}}
                    <div class="flex-shrink-0 w-7 h-7 rounded-full {{ $dotColor }} bg-opacity-15 border border-current/10 flex items-center justify-center"
                         style="background-color: color-mix(in srgb, {{ str_contains($dotColor,'emerald') ? '#10b981' : (str_contains($dotColor,'amber') ? '#f59e0b' : (str_contains($dotColor,'brand') ? '#9e2a2b' : '#94a3b8')) }} 12%, white)">
                        <svg class="w-3.5 h-3.5 {{ str_contains($dotColor,'emerald') ? 'text-emerald-600' : (str_contains($dotColor,'amber') ? 'text-amber-600' : (str_contains($dotColor,'brand') ? 'text-brand-600' : 'text-slate-500')) }}"
                             fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            {!! $iconPath !!}
                        </svg>
                    </div>

                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="text-xs font-semibold text-slate-800">{{ $label }}</span>
                            @if ($changed->count() > 0)
                                <span class="text-xs text-slate-400">· {{ $changed->count() }} employee{{ $changed->count() > 1 ? 's' : '' }} changed</span>
                            @elseif ($unchanged->count() > 0 && ($isSave || $isRecalc))
                                <span class="text-xs text-slate-400">· no changes</span>
                            @endif
                        </div>
                        <p class="text-xs text-slate-400 mt-0.5">
                            by {{ $entry->user?->name ?? 'System' }}
                            · <span title="{{ $entry->created_at->format('d M Y H:i:s') }}">{{ $entry->created_at->diffForHumans() }}</span>
                        </p>
                    </div>

                    {{-- Toggle expand (only for save/recalc with changes) --}}
                    @if (($isSave || $isRecalc) && $changed->count() > 0)
                        <button @click="expanded = !expanded"
                                class="flex-shrink-0 text-slate-400 hover:text-slate-600 transition-colors">
                            <svg class="w-4 h-4 transition-transform" :class="expanded ? 'rotate-180' : ''"
                                 fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/>
                            </svg>
                        </button>
                    @endif
                </div>

                {{-- Employee diff table --}}
                @if (($isSave || $isRecalc) && $changed->count() > 0)
                    <div x-show="expanded" x-collapse class="mt-3 ml-10">
                        <div class="rounded-lg border border-slate-100 overflow-hidden text-xs">
                            {{-- Column headers --}}
                            <div class="grid grid-cols-4 gap-0 bg-slate-50 px-3 py-2 text-slate-500 font-medium border-b border-slate-100">
                                <span>Employee</span>
                                <span class="text-right">Weekly Total</span>
                                <span class="text-right">Cash</span>
                                <span class="text-right">Bank</span>
                            </div>
                            {{-- Rows --}}
                            @foreach ($changed as $emp)
                                <div class="grid grid-cols-4 gap-0 px-3 py-2 border-b border-slate-50 last:border-0 hover:bg-slate-50/50">
                                    <span class="font-medium text-slate-700 truncate pr-2">{{ $emp['name'] }}</span>

                                    {{-- Weekly --}}
                                    <span class="text-right">
                                        @if (isset($emp['weekly']))
                                            @if ($emp['weekly']['from'] === null)
                                                <span class="text-brand-600 font-medium">{{ number_format($emp['weekly']['to'], 2) }}</span>
                                            @elseif ($emp['weekly']['to'] > $emp['weekly']['from'])
                                                <span class="text-slate-400 line-through mr-1">{{ number_format($emp['weekly']['from'], 2) }}</span><span class="text-emerald-600 font-medium">{{ number_format($emp['weekly']['to'], 2) }}</span>
                                            @elseif ($emp['weekly']['to'] < $emp['weekly']['from'])
                                                <span class="text-slate-400 line-through mr-1">{{ number_format($emp['weekly']['from'], 2) }}</span><span class="text-red-500 font-medium">{{ number_format($emp['weekly']['to'], 2) }}</span>
                                            @else
                                                <span class="text-slate-500">{{ number_format($emp['weekly']['to'], 2) }}</span>
                                            @endif
                                        @else
                                            <span class="text-slate-300">—</span>
                                        @endif
                                    </span>

                                    {{-- Cash --}}
                                    <span class="text-right">
                                        @if (isset($emp['cash']))
                                            @if ($emp['cash']['from'] === null)
                                                <span class="text-brand-600 font-medium">{{ number_format($emp['cash']['to'], 2) }}</span>
                                            @elseif ($emp['cash']['to'] !== $emp['cash']['from'])
                                                <span class="text-slate-400 line-through mr-1">{{ number_format($emp['cash']['from'], 2) }}</span><span class="text-slate-700 font-medium">{{ number_format($emp['cash']['to'], 2) }}</span>
                                            @else
                                                <span class="text-slate-500">{{ number_format($emp['cash']['to'], 2) }}</span>
                                            @endif
                                        @else
                                            <span class="text-slate-300">—</span>
                                        @endif
                                    </span>

                                    {{-- Bank --}}
                                    <span class="text-right">
                                        @if (isset($emp['bank']))
                                            @if ($emp['bank']['from'] === null)
                                                <span class="text-brand-600 font-medium">{{ number_format($emp['bank']['to'], 2) }}</span>
                                            @elseif ($emp['bank']['to'] !== $emp['bank']['from'])
                                                <span class="text-slate-400 line-through mr-1">{{ number_format($emp['bank']['from'], 2) }}</span><span class="text-slate-700 font-medium">{{ number_format($emp['bank']['to'], 2) }}</span>
                                            @else
                                                <span class="text-slate-500">{{ number_format($emp['bank']['to'], 2) }}</span>
                                            @endif
                                        @else
                                            <span class="text-slate-300">—</span>
                                        @endif
                                    </span>
                                </div>
                            @endforeach
                        </div>

                        {{-- Unchanged employees summary --}}
                        @if ($unchanged->count() > 0)
                            <p class="mt-2 text-xs text-slate-400">
                                {{ $unchanged->count() }} employee{{ $unchanged->count() > 1 ? 's' : '' }} had no changes.
                            </p>
                        @endif
                    </div>
                @endif

            </div>
        @empty
            {{-- Fallback when audit_logs table has no entries for this run yet --}}
            @if ($run)
                <div class="flex items-start gap-3 px-5 py-3.5">
                    <div class="flex-shrink-0 mt-0.5 w-7 h-7 rounded-full bg-slate-100 flex items-center justify-center">
                        <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
                        </svg>
                    </div>
                    <div class="flex-1">
                        <p class="text-xs font-semibold text-slate-800">Payroll run created</p>
                        <p class="text-xs text-slate-400 mt-0.5">Week {{ $week }}/{{ $year }}</p>
                    </div>
                    <span class="text-xs text-slate-400" title="{{ $run->created_at }}">
                        {{ \Carbon\Carbon::parse($run->created_at)->diffForHumans() }}
                    </span>
                </div>
            @else
                <div class="px-5 py-8 text-center text-xs text-slate-400">
                    No payroll run yet. Save payroll to start tracking activity.
                </div>
            @endif
        @endforelse

    </div>

</div>
