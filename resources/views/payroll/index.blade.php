@extends('layouts.app')
@section('title', 'Weekly payroll')
@section('page_title', 'Weekly payroll')
@section('page_header', 'Weekly Payroll')
@section('page_subtitle', 'Review, edit and finalise weekly gross pay for all staff.')

@section('page_action')
    <form method="get" action="{{ route('payroll.index') }}" class="flex flex-wrap items-center gap-3">
        <label class="sr-only">Year</label>
        <select name="year" class="rounded-lg border-slate-300 shadow-sm text-sm py-2 pl-3 pr-8 focus:ring-slate-500 focus:border-slate-500">
            @for ($y = now()->year - 2; $y <= now()->year + 10; $y++)
                <option value="{{ $y }}" @selected($y == $year)>{{ $y }}</option>
            @endfor
        </select>
        
        <label class="sr-only">Week</label>
        <select name="week" class="rounded-lg border-slate-300 shadow-sm text-sm py-2 pl-3 pr-8 focus:ring-slate-500 focus:border-slate-500">
            @for ($w = 1; $w <= $weeksInYear; $w++)
                <option value="{{ $w }}" @selected($w == $week)>Week {{ $w }}</option>
            @endfor
        </select>

        <button type="submit" class="px-4 py-2 rounded-lg bg-slate-900 text-white text-sm font-medium hover:bg-slate-800 transition shadow-sm">
            Load
        </button>

        @if ($run)
            <a href="{{ route('payroll.exportWeekCsv', ['year' => $year, 'week' => $week]) }}"
               class="px-3 py-2 rounded-lg border border-slate-300 bg-white text-sm font-medium text-slate-700 hover:bg-slate-50 shadow-sm transition">
                Export
            </a>
        @endif
        
        <a href="{{ route('payroll.weekly.report', ['year' => $year, 'week' => $week]) }}"
           class="px-3 py-2 rounded-lg border border-slate-300 bg-white text-sm font-medium text-slate-700 hover:bg-slate-50 shadow-sm transition">
            Report
        </a>
    </form>
@endsection

@section('content')

    <div x-data="payrollPage()" x-init="init(window.payrollServerRows)" class="space-y-6">


        @include('payroll.partials._status_banner')

        @include('payroll.partials._table')

    </div>

    @include('payroll.partials._script')

@endsection
