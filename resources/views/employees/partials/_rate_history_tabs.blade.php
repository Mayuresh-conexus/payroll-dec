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
            $tabRows = $ratesByType->get($tab['key'], collect());
            $current = $tabRows->first();
            $fallbackAmount = match ($tab['key']) {
                'daily_rate' => $employee->daily_rate,
                'hourly_rate' => $employee->hourly_rate,
                'hours_per_day' => $employee->hours_per_day,
            };
            $currentAmount = $current ? $current->amount : ($fallbackAmount ?? 0);
            $currentAmountLabel = $tab['key'] === 'hours_per_day'
                ? number_format($currentAmount, 1)
                : '€'.number_format($currentAmount, 2);
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

                @if ($tabRows->isEmpty())
                    <p class="text-sm text-slate-400">No entries for this rate type yet.</p>
                @else
                    <div class="relative pl-5">
                        <div class="absolute left-[5px] top-1.5 bottom-1.5 w-px bg-slate-200"></div>
                        <div class="space-y-5">
                            @foreach ($tabRows as $i => $rate)
                                @php
                                    $amountLabel = $rate->rate_type === 'hours_per_day'
                                        ? number_format($rate->amount, 1)
                                        : '€'.number_format($rate->amount, 2);
                                    $effectiveFromLabel = $rate->effective_from
                                        ? \Carbon\Carbon::parse($rate->effective_from)->format('d M Y')
                                        : '—';
                                @endphp
                                <div class="relative">
                                    <div class="absolute -left-5 top-1 w-2.5 h-2.5 rounded-full border-2 border-white {{ $i === 0 ? 'bg-brand-500' : 'bg-slate-300' }}"></div>

                                    <div class="flex items-center gap-4">
                                        <div class="flex-1">
                                            <p class="text-[10px] uppercase text-slate-400">Effective</p>
                                            <p class="text-sm font-medium text-slate-700">
                                                {{ $effectiveFromLabel }}
                                                @if ($i === 0)
                                                    <span class="ml-1 text-[10px] font-semibold text-brand-600">Current</span>
                                                @endif
                                            </p>
                                        </div>
                                        <div class="flex-1 text-right">
                                            <p class="text-[10px] uppercase text-slate-400">Rate</p>
                                            <p class="text-sm font-mono font-semibold text-slate-800">{{ $amountLabel }}</p>
                                        </div>
                                        <button type="button"
                                            @click="deleteTarget = { id: {{ $rate->id }}, amount: '{{ $amountLabel }}', effectiveFrom: '{{ $effectiveFromLabel }}' }; deleteModalOpen = true"
                                            data-tooltip="Delete"
                                            class="shrink-0 p-1 rounded-md hover:bg-rose-50 hover:text-rose-600 text-slate-300 transition">
                                            <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 7h12M10 11v6m4-6v6M9 4h6v3H9zM4 7h16l-1 13H5L4 7Z" />
                                            </svg>
                                        </button>
                                    </div>
                                    <p class="text-[11px] text-slate-400 mt-1">
                                        by {{ $creators->get($rate->created_by, '—') }}
                                        @if ($rate->created_at)
                                            · {{ $rate->created_at->format('d M Y, H:i') }}
                                        @endif
                                    </p>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </div>
    @endforeach
</div>
