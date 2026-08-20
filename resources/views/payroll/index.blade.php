@extends('layouts.app')
@section('title', 'Weekly payroll')
@section('page_title', 'Weekly payroll')
@section('page_header', 'Weekly Payroll')
@section('page_subtitle', 'Review, edit and finalise weekly gross pay for all staff.')

@section('page_action')
    <form method="get" action="{{ route('payroll.index') }}" class="flex flex-wrap items-center gap-3">
        @include('payroll.partials._week_range')

        <label class="sr-only">Year</label>
        <select name="year" class="rounded-lg border-slate-300 shadow-sm text-sm py-2 pl-3 pr-8 focus:ring-slate-500 focus:border-slate-500">
            @for ($y = now()->year - 2; $y <= now()->year + 10; $y++)
                <option value="{{ $y }}" @selected($y == $year)>{{ $y }}</option>
            @endfor
        </select>
        
        {{-- The "current week" note hangs off the selector it describes, rather
             than taking a line of its own further down the page. --}}
        <label class="sr-only">Week</label>
        <div class="inline-flex flex-col items-stretch">
            <select name="week" class="border-slate-300 shadow-sm text-sm py-2 pl-3 pr-8 focus:ring-slate-500 focus:border-slate-500 {{ $isCurrentWeek ? 'rounded-t-lg' : 'rounded-lg' }}">
                @for ($w = 1; $w <= $weeksInYear; $w++)
                    <option value="{{ $w }}" @selected($w == $week)>Week {{ $w }}</option>
                @endfor
            </select>
            @if ($isCurrentWeek)
                <span class="rounded-b-lg border border-t-0 border-emerald-200 bg-emerald-50 py-0.5 text-center text-[10px] font-semibold uppercase tracking-wide text-emerald-700"
                      title="This week is still running — attendance for it may not be complete.">
                    Current week
                </span>
            @endif
        </div>

        <button type="submit" class="px-4 py-2 rounded-lg bg-slate-900 text-white text-sm font-medium hover:bg-slate-800 transition shadow-sm">
            Load
        </button>

        {{-- Exporting and reporting live beside the week's own actions; the
             toolbar keeps only what changes which week you are looking at. --}}
    </form>
@endsection

@section('content')

    <div x-data="payrollPage()" x-init="init(@js($rows))" class="space-y-6">

        @include('payroll.partials._status_banner')

        @include('payroll.partials._table')

        @include('payroll.partials._history')

        @include('payroll.partials._settle_modal')

    </div>

    @include('payroll.partials._script')

@endsection
