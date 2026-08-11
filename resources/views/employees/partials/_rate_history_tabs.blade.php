{{-- employees/partials/_rate_history_tabs.blade.php — Compact rate reference panel (right column) --}}
<div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden"
    x-data="{ activeTab: '{{ $rateTypeTabs[0]['key'] ?? 'daily_rate' }}' }">

    {{-- Identity header --}}
    <div class="p-5 border-b border-slate-100">
        <h2 class="text-sm font-bold text-slate-800">{{ $employee->name }}</h2>
        <p class="mt-0.5 text-xs text-slate-400">{{ $employee->department ?? 'No department set' }}</p>
    </div>

    {{-- Small pill tabs --}}
    @if (count($rateTypeTabs) > 1)
        <div class="px-5 pt-4 flex gap-2 flex-wrap">
            @foreach ($rateTypeTabs as $tab)
                <button type="button" @click="activeTab = '{{ $tab['key'] }}'"
                    :class="activeTab === '{{ $tab['key'] }}' ? 'bg-brand-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                    class="px-3 py-1.5 rounded-full text-xs font-medium transition">
                    {{ $tab['label'] }}
                </button>
            @endforeach
        </div>
    @endif

    @foreach ($rateTypeTabs as $tab)
        @php
            // ->values() re-indexes to sequential 0-based keys — groupBy() (in the
            // controller) preserves each row's original position in the full,
            // mixed-rate-type history, so without this the "first item" of a tab
            // isn't necessarily at index 0 (e.g. an hourly employee whose hourly_rate
            // and hours_per_day changes are interleaved by date).
            $tabRows = $ratesByType->get($tab['key'], collect())->values();

            // The entry in effect *today* — a future-dated entry is scheduled, not current.
            $current = $employee->rateEntryAt($tab['key']);
            $upcoming = $employee->upcomingRateEntryOf($tab['key']);

            $fallbackAmount = match ($tab['key']) {
                'daily_rate' => $employee->daily_rate,
                'hourly_rate' => $employee->hourly_rate,
                'hours_per_day' => $employee->hours_per_day,
            };
            $currentAmount = $current ? $current->amount : ($fallbackAmount ?? 0);
            $formatTabAmount = fn ($amount) => $tab['key'] === 'hours_per_day'
                ? number_format($amount, 1).' hrs'
                : '€'.number_format($amount, 2);
            $currentAmountLabel = $formatTabAmount($currentAmount);
            $currentEffectiveFromLabel = $current && $current->effective_from
                ? \Carbon\Carbon::parse($current->effective_from)->format('d M Y')
                : '—';
        @endphp
        <div x-show="activeTab === '{{ $tab['key'] }}'" x-cloak>

            {{-- Current rate + key details card --}}
            <div class="mx-5 mt-4 rounded-lg border border-slate-200 bg-slate-50 p-4">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">Current {{ $tab['label'] }}</p>
                        <p class="mt-0.5 text-xl font-bold text-slate-800 font-mono">{{ $currentAmountLabel }}</p>
                    </div>
                    <div class="text-right">
                        <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">Effective From</p>
                        <p class="mt-0.5 text-sm text-slate-600">{{ $currentEffectiveFromLabel }}</p>
                    </div>
                </div>

                @if ($upcoming)
                    <p class="mt-2 flex items-center gap-1.5 text-[11px] text-sky-700">
                        <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                        </svg>
                        Changes to <strong class="font-mono">{{ $formatTabAmount($upcoming->amount) }}</strong>
                        on {{ \Carbon\Carbon::parse($upcoming->effective_from)->format('d M Y') }}
                    </p>
                @endif

                <div class="grid grid-cols-3 gap-3 mt-3 pt-3 border-t border-slate-200 text-xs">
                    <div>
                        <p class="text-slate-400">Code</p>
                        <p class="text-slate-700 font-medium font-mono">{{ $employee->employee_code }}</p>
                    </div>
                    <div>
                        <p class="text-slate-400">Joined</p>
                        <p class="text-slate-700 font-medium">{{ $employee->joining_date?->format('d M Y') ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-slate-400">Status</p>
                        @if ($employee->is_active)
                            <span class="inline-flex mt-0.5 px-1.5 py-0.5 rounded text-[10px] font-semibold bg-emerald-50 text-emerald-700">Active</span>
                        @else
                            <span class="inline-flex mt-0.5 px-1.5 py-0.5 rounded text-[10px] font-semibold bg-rose-50 text-rose-700">Inactive</span>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Rate change timeline --}}
            <div class="p-5">
                <h3 class="text-[10px] font-semibold uppercase tracking-wide text-slate-400 mb-3">Rate Change Timeline</h3>

                @php
                    // Rate changes and employee-level status changes share one timeline,
                    // ordered by the date each took effect.
                    $timeline = $tabRows
                        ->map(fn ($rate, $i) => ['kind' => 'rate', 'at' => $rate->effective_from, 'model' => $rate, 'i' => $i])
                        ->concat(
                            ($statusChanges ?? collect())->map(fn ($sc) => [
                                'kind' => 'status', 'at' => $sc->effective_from, 'model' => $sc, 'i' => null,
                            ])
                        )
                        ->sortByDesc(fn ($e) => [\Carbon\Carbon::parse($e['at'])->toDateString(), $e['kind'] === 'rate' ? 1 : 0])
                        ->values();
                @endphp

                @if ($timeline->isEmpty())
                    <p class="text-sm text-slate-400">No entries for this rate type yet.</p>
                @else
                    <div class="relative pl-5">
                        <div class="absolute left-[5px] top-1.5 bottom-1.5 w-px bg-slate-200"></div>
                        <div class="space-y-5">
                            @foreach ($timeline as $entry)
                                @if ($entry['kind'] === 'status')
                                    @php
                                        $sc = $entry['model'];
                                        $on = \Carbon\Carbon::parse($sc->effective_from)->format('d M Y');
                                    @endphp
                                    <div class="relative">
                                        <div class="absolute -left-5 top-1.5 w-2.5 h-2.5 rounded-full border-2 border-white {{ $sc->is_active ? 'bg-emerald-500' : 'bg-rose-500' }}"></div>
                                        <div class="rounded-lg -mx-2 px-3 py-2.5 border-l-2 {{ $sc->is_active ? 'bg-emerald-50/70 border-l-emerald-400' : 'bg-rose-50/70 border-l-rose-400' }}">
                                            <div class="flex items-center gap-2">
                                                <svg class="w-3.5 h-3.5 shrink-0 {{ $sc->is_active ? 'text-emerald-600' : 'text-rose-600' }}"
                                                    fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                    @if ($sc->is_active)
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                                                    @else
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 0 0 5.636 5.636m12.728 12.728A9 9 0 0 1 5.636 5.636m12.728 12.728L5.636 5.636"/>
                                                    @endif
                                                </svg>
                                                <span class="text-sm font-semibold {{ $sc->is_active ? 'text-emerald-800' : 'text-rose-800' }}">
                                                    {{ $sc->is_active ? 'Reactivated' : 'Deactivated' }}
                                                </span>
                                                <span class="text-xs text-slate-500">· {{ $on }}</span>
                                            </div>
                                            <p class="text-[11px] text-slate-400 mt-1">
                                                by {{ $creators->get($sc->created_by, '—') }}
                                                @if ($sc->created_at)
                                                    · {{ $sc->created_at->format('d M Y, H:i') }}
                                                @endif
                                            </p>
                                        </div>
                                    </div>
                                    @continue
                                @endif

                                @php
                                    $i = $entry['i'];
                                    $rate = $entry['model'];
                                    $isHours = $rate->rate_type === 'hours_per_day';
                                    $formatAmount = fn ($amount) => $isHours
                                        ? number_format($amount, 1).' hrs'
                                        : '€'.number_format($amount, 2);

                                    $currentAmount = (float) $rate->amount;
                                    $amountLabel = $formatAmount($currentAmount);
                                    $effectiveFromLabel = $rate->effective_from
                                        ? \Carbon\Carbon::parse($rate->effective_from)->format('d M Y')
                                        : '—';

                                    // $tabRows is sorted newest-first, so the entry that was
                                    // superseded by this one sits at the next index.
                                    $previousRate = $tabRows->get($i + 1);
                                    $previousAmount = $previousRate ? (float) $previousRate->amount : null;
                                    $previousAmountLabel = $previousAmount !== null ? $formatAmount($previousAmount) : null;

                                    // "Current" belongs to the entry in effect today, not simply
                                    // the newest one — anything dated ahead is merely scheduled.
                                    $isScheduled = $rate->effective_from
                                        && \Carbon\Carbon::parse($rate->effective_from)->toDateString() > now()->toDateString();
                                    $isCurrentEntry = $current && $current->id === $rate->id;

                                    $delta = $previousAmount !== null ? $currentAmount - $previousAmount : null;
                                    // Guard against divide-by-zero when the superseded rate was 0.
                                    $percentChange = ($previousAmount !== null && abs($previousAmount) > 0.00001)
                                        ? ($delta / $previousAmount) * 100
                                        : null;
                                    $isUnchanged = $delta !== null && abs($delta) < 0.00001;
                                @endphp
                                <div class="relative">
                                    <div class="absolute -left-5 top-1.5 w-2.5 h-2.5 rounded-full border-2 border-white {{ $isCurrentEntry ? 'bg-brand-500' : ($isScheduled ? 'bg-sky-400' : 'bg-slate-300') }}"></div>

                                    {{-- Highlight the entry in effect today. Negative margin keeps
                                         its text aligned with the other rows while the tint bleeds out. --}}
                                    <div class="{{ $isCurrentEntry ? 'bg-brand-50/70 rounded-lg -mx-2 px-2 py-2' : '' }}">

                                    {{-- Effective date + delete action --}}
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="min-w-0">
                                            <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">Effective</p>
                                            <p class="text-sm font-medium text-slate-700">
                                                {{ $effectiveFromLabel }}
                                                @if ($isCurrentEntry)
                                                    <span class="ml-1 text-[10px] font-semibold text-brand-600">Current</span>
                                                @elseif ($isScheduled)
                                                    <span class="ml-1 inline-flex px-1.5 py-0.5 rounded text-[9px] font-bold uppercase tracking-wide bg-sky-50 text-sky-700">Scheduled</span>
                                                @endif
                                            </p>
                                        </div>
                                        <button type="button"
                                            @click="deleteTarget = { id: {{ $rate->id }}, amount: '{{ $amountLabel }}', effectiveFrom: '{{ $effectiveFromLabel }}' }; deleteConfirmText = ''; deleteModalOpen = true"
                                            data-tooltip="Delete"
                                            class="shrink-0 p-1 rounded-md hover:bg-rose-50 hover:text-rose-600 text-slate-300 transition">
                                            <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 7h12M10 11v6m4-6v6M9 4h6v3H9zM4 7h16l-1 13H5L4 7Z" />
                                            </svg>
                                        </button>
                                    </div>

                                    {{-- The change itself: old (muted) → new (emphasised) + delta --}}
                                    <div class="mt-1.5 flex items-center gap-2 flex-wrap">
                                        @if ($previousAmountLabel)
                                            <span class="text-sm font-mono text-slate-400">{{ $previousAmountLabel }}</span>
                                            <svg class="w-4 h-4 text-slate-300 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                                            </svg>
                                        @endif

                                        <span class="text-lg font-mono font-bold text-slate-900">{{ $amountLabel }}</span>

                                        @if ($isUnchanged)
                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold bg-slate-100 text-slate-500">
                                                No change
                                            </span>
                                        @elseif ($delta !== null && $delta > 0)
                                            <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-bold bg-emerald-50 text-emerald-700">
                                                <svg class="w-2.5 h-2.5" fill="currentColor" viewBox="0 0 20 20"><path d="M10 5 15 13 5 13 Z" /></svg>
                                                @if ($percentChange !== null)
                                                    {{ number_format(abs($percentChange), 1) }}%
                                                @else
                                                    +{{ $formatAmount($delta) }}
                                                @endif
                                            </span>
                                        @elseif ($delta !== null && $delta < 0)
                                            <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-bold bg-rose-50 text-rose-700">
                                                <svg class="w-2.5 h-2.5" fill="currentColor" viewBox="0 0 20 20"><path d="M10 15 5 7 15 7 Z" /></svg>
                                                @if ($percentChange !== null)
                                                    {{ number_format(abs($percentChange), 1) }}%
                                                @else
                                                    {{ $formatAmount($delta) }}
                                                @endif
                                            </span>
                                        @else
                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold bg-slate-100 text-slate-500">
                                                Initial rate
                                            </span>
                                        @endif
                                    </div>

                                    <p class="text-[11px] text-slate-400 mt-1.5">
                                        by {{ $creators->get($rate->created_by, '—') }}
                                        @if ($rate->created_at)
                                            · {{ $rate->created_at->format('d M Y, H:i') }}
                                        @endif
                                    </p>

                                    </div>{{-- /highlight --}}
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </div>
    @endforeach
</div>
