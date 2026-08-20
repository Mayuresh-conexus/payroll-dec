@extends('layouts.app')

@section('title', 'Backup diagnostics')
@section('page_title', 'Backup diagnostics')
@section('page_header', 'Backup Diagnostics')
@section('page_subtitle', "What backup and restore depend on, checked against the web server's own PHP.")

@section('page_action')
    <div class="flex items-center gap-3">
        <a href="{{ route('backups.diagnostics') }}"
            class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg border border-slate-300 bg-white text-sm font-medium text-slate-700 hover:bg-slate-50 transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99"/>
            </svg>
            Run again
        </a>
        <a href="{{ route('backups.index') }}"
            class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg border border-slate-200 bg-white text-sm text-slate-700 hover:bg-slate-50 transition">
            ← Back to Backups
        </a>
    </div>
@endsection

@section('content')
<div class="space-y-5">

    {{-- Which PHP answered. The whole reason this page exists: a terminal runs a
         different php.ini on most shared hosts, so a diagnosis there can describe
         an environment the restore never runs in. --}}
    <div class="rounded-lg border px-4 py-3 text-sm {{ $report['ok'] ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-rose-200 bg-rose-50 text-rose-800' }}">
        <strong>{{ $report['ok'] ? 'All checks passed.' : 'Something here will stop backup or restore working.' }}</strong>
        <span class="opacity-80">
            Checked against <span class="font-mono">{{ $report['sapi'] }}</span> — the PHP that actually runs your backups.
        </span>
    </div>

    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <table class="min-w-full text-sm">
            <tbody class="divide-y divide-slate-100">
                @foreach ($report['checks'] as $check)
                    <tr>
                        <td class="px-4 py-3 w-8 align-top">
                            @if ($check['status'] === \App\Services\BackupEnvironmentReport::PASS)
                                <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                            @elseif ($check['status'] === \App\Services\BackupEnvironmentReport::FAIL)
                                <svg class="w-4 h-4 text-rose-600" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                            @else
                                <span class="block w-4 text-center font-bold text-amber-500">–</span>
                            @endif
                        </td>
                        <td class="px-2 py-3 w-52 font-medium text-slate-800 align-top">{{ $check['label'] }}</td>
                        <td class="px-4 py-3 text-slate-600 align-top">
                            <span class="font-mono text-xs break-all">{{ $check['detail'] }}</span>
                            @if ($check['elapsed'] !== null)
                                <span class="ml-1 text-slate-400 text-xs">({{ $check['elapsed'] >= 1 ? round($check['elapsed'], 1).'s' : round($check['elapsed'] * 1000).'ms' }})</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="bg-white rounded-xl border border-slate-200 shadow-sm px-4 py-3 space-y-2">
        <div class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Loaded configuration</div>
        <div class="font-mono text-xs text-slate-600 break-all">{{ $report['ini'] }}</div>

        <div class="pt-1 text-[11px] font-semibold uppercase tracking-wide text-slate-400">disable_functions</div>
        <div class="font-mono text-xs text-slate-600 break-all">{{ $report['disable_functions'] ?: '(empty)' }}</div>
    </div>

</div>
@endsection
