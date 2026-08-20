{{-- payroll/partials/_week_range.blade.php — which seven days this payroll covers

     Sits in front of the week selector rather than on a strip of its own: the
     selector says which week, these say what that means in dates. No labels —
     two dates with an arrow between them already read as a range, and the week
     number is right beside them in the dropdown. --}}

@php
    $rangeStart = \Carbon\Carbon::now()->setISODate((int) $year, (int) $week, 1)->startOfDay();
    $rangeEnd = $rangeStart->copy()->addDays(6);
@endphp

<div class="inline-flex items-center gap-2"
     aria-label="Payroll week {{ $week }}: {{ $rangeStart->format('j M Y') }} to {{ $rangeEnd->format('j M Y') }}">

    <div class="w-[84px] overflow-hidden rounded-lg border border-slate-200 shadow-sm">
        <div class="bg-slate-900 px-2 py-1.5 text-center text-[13px] font-bold text-white">{{ $rangeStart->format('j M') }}</div>
        <div class="bg-white px-2 py-0.5 text-center text-[10px] font-semibold uppercase tracking-wide text-slate-400">{{ $rangeStart->format('D') }}</div>
    </div>

    <span class="select-none text-slate-300" aria-hidden="true">&rarr;</span>

    <div class="w-[84px] overflow-hidden rounded-lg border border-slate-200 shadow-sm">
        <div class="bg-slate-900 px-2 py-1.5 text-center text-[13px] font-bold text-white">{{ $rangeEnd->format('j M') }}</div>
        <div class="bg-white px-2 py-0.5 text-center text-[10px] font-semibold uppercase tracking-wide text-slate-400">{{ $rangeEnd->format('D') }}</div>
    </div>
</div>
