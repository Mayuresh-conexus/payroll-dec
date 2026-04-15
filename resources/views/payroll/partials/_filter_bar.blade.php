{{-- payroll/partials/_filter_bar.blade.php — Year/week selector + export links --}}
<form method="get" action="{{ route('payroll.index') }}"
    class="bg-white rounded-xl shadow-sm border border-slate-200 p-4 flex flex-wrap items-center gap-4 text-sm">
    <div>
        <label class="block text-xs font-semibold text-slate-600 mb-1">Year</label>
        <select name="year" class="rounded-lg border-slate-200 text-sm focus:ring-slate-500 focus:border-slate-500">
            @for ($y = now()->year - 2; $y <= now()->year + 10; $y++)
                <option value="{{ $y }}" @selected($y == $year)>{{ $y }}</option>
            @endfor
        </select>
    </div>

    <div>
        <label class="block text-xs font-semibold text-slate-600 mb-1">Week</label>
        <select name="week" class="rounded-lg border-slate-200 text-sm focus:ring-slate-500 focus:border-slate-500">
            @for ($w = 1; $w <= $weeksInYear; $w++)
                <option value="{{ $w }}" @selected($w == $week)>Week {{ $w }}</option>
            @endfor
        </select>
    </div>

    <div class="flex items-end gap-3">
        <button type="submit"
            class="px-4 py-2 rounded-lg bg-slate-900 text-white text-sm font-medium hover:bg-slate-800">
            Load payroll
        </button>

        @if ($run)
            <a href="{{ route('payroll.exportWeekCsv', ['year' => $year, 'week' => $week]) }}"
                class="px-4 py-2 rounded-lg border border-slate-300 text-sm font-medium text-slate-700 hover:bg-slate-50">
                Export XLSX
            </a>
        @endif

        <a href="{{ route('payroll.weekly.report', ['year' => $year, 'week' => $week]) }}"
            class="inline-flex items-center px-4 py-2 rounded-lg border border-slate-200 text-sm font-medium text-slate-700 hover:bg-slate-50">
            View attendance report
        </a>
    </div>

    <div class="ml-auto text-xs text-slate-500">Week {{ $week }} / {{ $year }}</div>
</form>
