{{-- payroll/partials/_status_banner.blade.php — Finalization status (final / draft + finalize button) --}}
@if ($run)
    @if ($run->status === 'final')
        <div class="rounded-lg bg-emerald-50 border border-emerald-200 px-4 py-3 text-sm text-emerald-800 flex items-center gap-3">
            <svg class="w-5 h-5 flex-shrink-0 text-emerald-600" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.955 11.955 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" />
            </svg>
            <div class="flex-1">
                <strong>Week {{ $week }}/{{ $year }} is finalized</strong> — this payroll is locked and cannot be edited.
            </div>
        </div>
    @else
        <div class="rounded-lg bg-slate-50 border border-slate-200 px-4 py-3 text-sm text-slate-700 flex items-center justify-between gap-3">
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center gap-1.5 px-2 py-1 rounded-full text-xs bg-amber-50 text-amber-700 border border-amber-100">
                    <span class="w-1.5 h-1.5 rounded-full bg-amber-400"></span> Draft
                </span>
                <span class="text-slate-500">Week {{ $week }}/{{ $year }} — save edits above, then finalize when approved.</span>
            </div>
            <form action="{{ route('payroll.finalizeWeek') }}" method="POST"
                onsubmit="return confirm('Finalize payroll for Week {{ $week }}/{{ $year }}? This will lock it permanently.')">
                @csrf
                <input type="hidden" name="year" value="{{ $year }}">
                <input type="hidden" name="week" value="{{ $week }}">
                <button type="submit"
                    class="px-4 py-2 rounded-lg bg-emerald-700 text-white text-sm font-medium hover:bg-emerald-800">
                    ✓ Finalize Week
                </button>
            </form>
        </div>
    @endif
@endif
