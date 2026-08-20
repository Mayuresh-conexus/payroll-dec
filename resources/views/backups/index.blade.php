@extends('layouts.app')

@section('title', 'Backups')
@section('page_title', 'Backups')
@section('page_header', 'Database Backups')
@section('page_subtitle', 'Create, restore, download, and manage MySQL backups.')

@section('page_action')
    <div class="flex items-center gap-3">
        {{-- Reachable from here rather than only from a terminal: on shared
             hosting the CLI runs a different php.ini, so a diagnosis there can
             describe an environment the restore never runs in. --}}
        <a href="{{ route('backups.diagnostics') }}"
            class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg border border-slate-300 bg-white text-sm font-medium text-slate-700 hover:bg-slate-50 transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25h.75v4.5m-.75 0h1.5M12 7.5h.008v.008H12V7.5Z"/>
                <circle cx="12" cy="12" r="9"/>
            </svg>
            Diagnostics
        </a>

        <form method="POST" action="{{ route('backups.run') }}" onsubmit="return confirm('Create a new backup now?')">
            @csrf
            <button type="submit"
                class="inline-flex items-center px-4 py-2 rounded-lg text-sm font-medium bg-slate-900 text-white hover:bg-slate-800 transition">
                + Backup Now
            </button>
        </form>
    </div>
@endsection

@section('content')

    <div x-data="backupsPage()" class="space-y-6"
        x-init="
            @if ($errors->has('confirmation') && old('_modal') === 'restore')
                restoreModalOpen = true;
                restoreTarget = @json($backups->firstWhere('filename', old('_target_filename')));
                restoreConfirmText = @json(old('confirmation'));
            @endif
        ">

        @if (session('restore_completed'))
            <div class="rounded-lg bg-emerald-50 border border-emerald-200 px-4 py-3 flex items-start gap-3">
                <svg class="w-5 h-5 text-emerald-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                </svg>
                <div class="text-sm text-emerald-800">
                    <p class="font-semibold">Restore complete</p>
                    <p class="text-xs text-emerald-700 mt-0.5">{{ session('restore_completed') }}</p>
                </div>
            </div>
        @endif

        @if ($errors->has('backup'))
            <div class="rounded-lg bg-rose-50 border border-rose-200 px-4 py-3 text-sm text-rose-700">
                {{ $errors->first('backup') }}
            </div>
        @endif
        @if ($errors->has('restore'))
            <div class="rounded-lg bg-rose-50 border border-rose-200 px-4 py-3 text-sm text-rose-700">
                {{ $errors->first('restore') }}
            </div>
        @endif
        @if ($errors->has('delete'))
            <div class="rounded-lg bg-rose-50 border border-rose-200 px-4 py-3 text-sm text-rose-700">
                {{ $errors->first('delete') }}
            </div>
        @endif
        @if ($errors->has('confirmation'))
            <div class="rounded-lg bg-rose-50 border border-rose-200 px-4 py-3 text-sm text-rose-700">
                {{ $errors->first('confirmation') }}
            </div>
        @endif

        @include('backups.partials._table')

        @include('backups.partials._schedule')

        @include('backups.partials._history')

        @include('backups.partials._restore_modal')

        @include('backups.partials._delete_modal')

    </div>

    <script>
        function backupsPage() {
            return {
                restoreModalOpen: false,
                restoreTarget: null,
                restoreConfirmText: '',
                restoring: false,
                restoreSeconds: 0,
                restoreTimer: null,
                deleteModalOpen: false,
                deleteTarget: null,
                openRestore(backup) {
                    this.restoreTarget = backup;
                    this.restoreConfirmText = '';
                    this.restoring = false;
                    this.restoreSeconds = 0;
                    this.restoreModalOpen = true;
                },

                /**
                 * Restore is a synchronous request, so swap the dialog into a busy
                 * state and warn against closing the tab. The elapsed counter keeps
                 * the spinner honest: a healthy restore of this database finishes in
                 * about a second, so a climbing number is itself the diagnosis.
                 */
                beginRestore() {
                    this.restoring = true;
                    this.restoreSeconds = 0;
                    clearInterval(this.restoreTimer);
                    this.restoreTimer = setInterval(() => this.restoreSeconds++, 1000);
                    window.onbeforeunload = () => true;
                },
                openDelete(backup) {
                    this.deleteTarget = backup;
                    this.deleteModalOpen = true;
                },
            }
        }
    </script>

@endsection
