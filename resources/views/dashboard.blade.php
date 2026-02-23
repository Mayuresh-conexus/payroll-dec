@extends('layouts.app')

@section('title', 'Dashboard')
@section('page_title', 'Dashboard')

@section('content')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <div class="space-y-6" x-data="{ trends: {{ json_encode($payrollTrends) }} }">
        
        {{-- Header & Quick Stats --}}
        <div class="flex flex-col md:flex-row md:items-end justify-between gap-4">
            <div>
                <h1 class="text-3xl font-bold tracking-tight text-slate-900">Overview</h1>
                <p class="mt-1 text-sm text-slate-500">
                    Year {{ $currentYear }} - Week {{ $currentWeek }} ({{ $today->startOfWeek()->format('M d') }} - {{ $today->copy()->endOfWeek()->format('M d') }})
                </p>
            </div>
            
            <div class="flex items-center gap-2">
                @if (auth()->user()->hasRole('admin', 'manager'))
                    <a href="{{ route('attendance.index') }}" class="inline-flex items-center justify-center rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-emerald-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600 transition">
                        Mark Attendance
                    </a>
                @endif
                @if (auth()->user()->hasRole('admin'))
                    <a href="{{ route('payroll.index') }}" class="inline-flex items-center justify-center rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-slate-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900 transition">
                        Run Payroll
                    </a>
                @endif
            </div>
        </div>

        {{-- Top KPI Cards --}}
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            {{-- KPI: Employees --}}
            <div class="relative overflow-hidden rounded-2xl bg-white p-6 shadow-sm border border-slate-200">
                <dt>
                    <div class="absolute rounded-xl bg-blue-50 p-3">
                        <svg class="h-6 w-6 text-blue-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
                        </svg>
                    </div>
                    <p class="ml-16 truncate text-sm font-medium text-slate-500">Active Staff</p>
                </dt>
                <dd class="ml-16 flex items-baseline pb-1">
                    <p class="text-2xl font-semibold text-slate-900">{{ $employeeStats['active'] ?? 0 }}</p>
                    <p class="ml-2 text-sm text-slate-500">of {{ $employeeStats['total'] ?? 0 }} total</p>
                </dd>
            </div>

            {{-- KPI: Attendance --}}
            <div class="relative overflow-hidden rounded-2xl bg-white p-6 shadow-sm border border-slate-200">
                <dt>
                    <div class="absolute rounded-xl bg-emerald-50 p-3">
                        <svg class="h-6 w-6 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
                        </svg>
                    </div>
                    <p class="ml-16 truncate text-sm font-medium text-slate-500">Daily Presence</p>
                </dt>
                <dd class="ml-16 flex items-baseline pb-1">
                    <p class="text-2xl font-semibold text-slate-900">{{ $attendanceStats['daily_present'] ?? 0 }}</p>
                    <p class="ml-2 text-sm text-slate-500">days</p>
                </dd>
            </div>

            {{-- KPI: Gross Payroll --}}
            <div class="relative overflow-hidden rounded-2xl bg-white p-6 shadow-sm border border-slate-200">
                <dt>
                    <div class="absolute rounded-xl bg-indigo-50 p-3">
                        <svg class="h-6 w-6 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75M15 10.5a3 3 0 11-6 0 3 3 0 016 0zm3 0h.008v.008H18V10.5zm-12 0h.008v.008H6V10.5z" />
                        </svg>
                    </div>
                    <p class="ml-16 truncate text-sm font-medium text-slate-500">Last Gross Paid</p>
                </dt>
                <dd class="ml-16 flex items-baseline pb-1">
                    <p class="text-2xl font-semibold text-slate-900">₹{{ number_format($payrollStats['total_gross'] ?? 0) }}</p>
                </dd>
            </div>

            {{-- KPI: Bank Transfer --}}
            <div class="relative overflow-hidden rounded-2xl bg-white p-6 shadow-sm border border-slate-200">
                <dt>
                    <div class="absolute rounded-xl bg-amber-50 p-3">
                        <svg class="h-6 w-6 text-amber-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 21v-8.25M15.75 21v-8.25M8.25 21v-8.25M3 9l9-6 9 6m-1.5 12V10.332A48.36 48.36 0 0012 9.75c-2.551 0-5.056.2-7.5.582V21M3 21h18M12 6.75h.008v.008H12V6.75z" />
                        </svg>
                    </div>
                    <p class="ml-16 truncate text-sm font-medium text-slate-500">Last Bank Transfer</p>
                </dt>
                <dd class="ml-16 flex items-baseline pb-1">
                    <p class="text-2xl font-semibold text-slate-900">₹{{ number_format($payrollStats['total_bank'] ?? 0) }}</p>
                    <p class="ml-2 text-sm text-slate-500">cash: ₹{{ number_format($payrollStats['total_cash'] ?? 0) }}</p>
                </dd>
            </div>
        </div>

        {{-- Enterprise Charts Section --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            
            {{-- Main Chart: Payroll Trends (Spans 2 columns) --}}
            <div class="lg:col-span-2 rounded-2xl bg-white shadow-sm border border-slate-200 p-6">
                <h3 class="text-base font-semibold text-slate-900">Payroll Cost Trend</h3>
                <p class="text-sm text-slate-500 mb-6">Gross amount split between Cash & Bank (Last 12 Runs)</p>
                
                <div class="relative h-[300px] w-full">
                    <canvas id="payrollTrendChart"></canvas>
                </div>
            </div>

            {{-- Secondary Chart: Last Week Breakdown or Attendance Distribution --}}
            <div class="rounded-2xl bg-white shadow-sm border border-slate-200 p-6 flex flex-col">
                <h3 class="text-base font-semibold text-slate-900">Latest Cost Breakdown</h3>
                <p class="text-sm text-slate-500 mb-6">Distribution for Week {{ $payrollStats['week'] ?? '-' }}/{{ $payrollStats['year'] ?? '-' }}</p>
                
                <div class="relative flex-1 flex items-center justify-center p-4">
                    <canvas id="lastPayrollDoughnut"></canvas>
                </div>
                
                @if($payrollStats['has_run'])
                <div class="mt-4 border-t border-slate-100 pt-4">
                    <div class="flex justify-between items-center text-sm">
                        <span class="text-slate-500 flex items-center gap-2"><span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span> Cash Remittance</span>
                        <span class="font-medium">₹{{ number_format($payrollStats['total_cash'], 2) }}</span>
                    </div>
                    <div class="flex justify-between items-center text-sm mt-2">
                        <span class="text-slate-500 flex items-center gap-2"><span class="w-2.5 h-2.5 rounded-full bg-blue-500"></span> Bank Transfers</span>
                        <span class="font-medium">₹{{ number_format($payrollStats['total_bank'], 2) }}</span>
                    </div>
                </div>
                @endif
            </div>

        </div>

        {{-- Quick Highlights & Action Links --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            
            {{-- Recent Payroll Runs --}}
            <div class="rounded-2xl bg-white border border-slate-200 p-6 shadow-sm">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-base font-semibold text-slate-900">Recent Payroll Runs</h3>
                    <a href="{{ route('payroll.index') }}" class="text-xs text-slate-500 hover:text-slate-800 transition">View all →</a>
                </div>

                @if(count($recentRuns) > 0)
                <div class="overflow-x-auto -mx-6">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-slate-100">
                                <th class="text-left font-medium text-slate-500 text-xs uppercase tracking-wider px-6 pb-3">Week</th>
                                <th class="text-right font-medium text-slate-500 text-xs uppercase tracking-wider px-3 pb-3">Staff</th>
                                <th class="text-right font-medium text-slate-500 text-xs uppercase tracking-wider px-3 pb-3">Gross</th>
                                <th class="text-right font-medium text-slate-500 text-xs uppercase tracking-wider px-3 pb-3">Cash</th>
                                <th class="text-right font-medium text-slate-500 text-xs uppercase tracking-wider px-6 pb-3">Change</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50">
                            @foreach($recentRuns as $run)
                            <tr class="hover:bg-slate-50/50 transition">
                                <td class="px-6 py-2.5">
                                    <span class="font-medium text-slate-800">W{{ $run['week'] }}</span>
                                    <span class="text-slate-400 text-xs ml-1">'{{ substr($run['year'], 2) }}</span>
                                </td>
                                <td class="px-3 py-2.5 text-right">
                                    <span class="inline-flex items-center justify-center w-6 h-6 rounded-md bg-slate-100 text-slate-700 text-xs font-medium">{{ $run['employees'] }}</span>
                                </td>
                                <td class="px-3 py-2.5 text-right font-medium text-slate-800">₹{{ number_format($run['gross']) }}</td>
                                <td class="px-3 py-2.5 text-right text-slate-500">₹{{ number_format($run['cash']) }}</td>
                                <td class="px-6 py-2.5 text-right">
                                    @if($run['change_pct'] !== null)
                                        @if($run['change_pct'] > 0)
                                            <span class="inline-flex items-center gap-0.5 text-xs font-medium text-emerald-600">
                                                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7" /></svg>
                                                {{ $run['change_pct'] }}%
                                            </span>
                                        @elseif($run['change_pct'] < 0)
                                            <span class="inline-flex items-center gap-0.5 text-xs font-medium text-rose-500">
                                                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" /></svg>
                                                {{ abs($run['change_pct']) }}%
                                            </span>
                                        @else
                                            <span class="text-xs text-slate-400">—</span>
                                        @endif
                                    @else
                                        <span class="text-xs text-slate-300">—</span>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @else
                    <p class="text-sm text-slate-400 text-center py-6">No payroll runs recorded yet.</p>
                @endif
            </div>

            {{-- Quick Links --}}
            <div class="rounded-2xl bg-white border border-slate-200 p-6 shadow-sm">
                <h3 class="text-base font-semibold text-slate-900 mb-4">Administrative Shortcuts</h3>
                <div class="grid grid-cols-2 gap-3">
                    <a href="{{ route('employees.index') }}" class="group flex items-center gap-3 rounded-xl border border-slate-200 p-3 hover:bg-slate-50 transition">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-white shadow-sm border border-slate-100 group-hover:border-slate-300">
                            <svg class="h-5 w-5 text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0z" /></svg>
                        </div>
                        <div><p class="text-sm font-medium text-slate-900">Manage Staff</p><p class="text-xs text-slate-500">Edit profiles & rates</p></div>
                    </a>
                    
                    <a href="{{ route('payroll.monthly.index') }}" class="group flex items-center gap-3 rounded-xl border border-slate-200 p-3 hover:bg-slate-50 transition">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-white shadow-sm border border-slate-100 group-hover:border-slate-300">
                            <svg class="h-5 w-5 text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5" /></svg>
                        </div>
                        <div><p class="text-sm font-medium text-slate-900">Monthly View</p><p class="text-xs text-slate-500">View pro-rata data</p></div>
                    </a>
                    
                    <a href="{{ route('payroll.weekly.report') }}" class="group flex items-center gap-3 rounded-xl border border-slate-200 p-3 hover:bg-slate-50 transition">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-white shadow-sm border border-slate-100 group-hover:border-slate-300">
                            <svg class="h-5 w-5 text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" /></svg>
                        </div>
                        <div><p class="text-sm font-medium text-slate-900">Export Report</p><p class="text-xs text-slate-500">Attendance summaries</p></div>
                    </a>

                    <a href="{{ route('attendance.index', ['tab' => 'daily']) }}" class="group flex items-center gap-3 rounded-xl border border-slate-200 p-3 hover:bg-slate-50 transition">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-white shadow-sm border border-slate-100 group-hover:border-slate-300">
                            <svg class="h-5 w-5 text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        </div>
                        <div><p class="text-sm font-medium text-slate-900">Current Week</p><p class="text-xs text-slate-500">Input daily attendance</p></div>
                    </a>
                </div>
            </div>

        </div>
    </div>

    {{-- Script for initializing Chart.js --}}
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Chart global defaults for enterprise look
            Chart.defaults.font.family = "'Inter', 'Helvetica Neue', 'Helvetica', 'Arial', sans-serif";
            Chart.defaults.color = '#64748b'; // slate-500

            // Base data passed from Alpine context (which is json encoded in x-data)
            // But we will inject directly here since we are in blade
            const trends = @json($payrollTrends);
            
            if (trends.length > 0) {
                // Prepare data for line/bar chart
                const labels = trends.map(t => t.label);
                const cashData = trends.map(t => t.cash);
                const bankData = trends.map(t => t.bank);
                
                // 1. Payroll Trend Stacked Bar Chart
                const ctxTrend = document.getElementById('payrollTrendChart').getContext('2d');
                new Chart(ctxTrend, {
                    type: 'bar',
                    data: {
                        labels: labels,
                        datasets: [
                            {
                                label: 'Bank Transfer',
                                data: bankData,
                                backgroundColor: '#3b82f6', // blue-500
                                borderRadius: 4,
                            },
                            {
                                label: 'Cash Payment',
                                data: cashData,
                                backgroundColor: '#10b981', // emerald-500
                                borderRadius: 4,
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: {
                            mode: 'index',
                            intersect: false,
                        },
                        plugins: {
                            legend: {
                                position: 'top',
                                align: 'end',
                                labels: { boxWidth: 12, usePointStyle: true, pointStyle: 'circle' }
                            },
                            tooltip: {
                                backgroundColor: '#1e293b',
                                titleFont: { size: 13, weight: 'bold' },
                                bodyFont: { size: 13 },
                                padding: 10,
                                cornerRadius: 8,
                                callbacks: {
                                    label: function(context) {
                                        let label = context.dataset.label || '';
                                        if (label) { label += ': '; }
                                        if (context.parsed.y !== null) {
                                            label += new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR', maximumFractionDigits: 0 }).format(context.parsed.y);
                                        }
                                        return label;
                                    }
                                }
                            }
                        },
                        scales: {
                            x: {
                                stacked: true,
                                grid: { display: false, drawBorder: false }
                            },
                            y: {
                                stacked: true,
                                grid: { color: '#f1F5f9', drawBorder: false }, // slate-100
                                border: { dash: [4, 4] },
                                ticks: {
                                    callback: function(value) { return '₹' + value; }
                                }
                            }
                        }
                    }
                });

                // 2. Latest Payroll Doughnut Chart
                const latestTrend = trends[trends.length - 1]; // We reversed the array, so latest might be at index 0 or length-1 depending on order
                // Actually the data is ordered by year/week desc, then reversed.
                // Reversing means index trends.length-1 is the most recent.
                
                const ctxDoughnut = document.getElementById('lastPayrollDoughnut');
                if(ctxDoughnut) {
                    new Chart(ctxDoughnut.getContext('2d'), {
                        type: 'doughnut',
                        data: {
                            labels: ['Cash', 'Bank'],
                            datasets: [{
                                data: [latestTrend.cash, latestTrend.bank],
                                backgroundColor: ['#10b981', '#3b82f6'],
                                hoverBackgroundColor: ['#059669', '#2563eb'],
                                borderWidth: 0,
                                hoverOffset: 4
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            cutout: '75%',
                            plugins: {
                                legend: { display: false },
                                tooltip: {
                                    backgroundColor: '#1e293b',
                                    callbacks: {
                                        label: function(context) {
                                            return ' ' + context.label + ': ' + new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR', maximumFractionDigits: 0 }).format(context.raw);
                                        }
                                    }
                                }
                            }
                        }
                    });
                }
            }
        });
    </script>
@endsection

