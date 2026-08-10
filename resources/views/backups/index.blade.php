@extends('layouts.app')

@section('title', 'Backups')
@section('page_title', 'Backups')
@section('page_header', 'Database Backups')
@section('page_subtitle', 'Create, restore, download, and manage MySQL backups.')

@section('page_action')
    <form method="POST" action="{{ route('backups.run') }}" onsubmit="return confirm('Create a new backup now?')">
        @csrf
        <button type="submit"
            class="inline-flex items-center px-4 py-2 rounded-lg text-sm font-medium bg-slate-900 text-white hover:bg-slate-800 transition">
            + Backup Now
        </button>
    </form>
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
                deleteModalOpen: false,
                deleteTarget: null,
                openRestore(backup) {
                    this.restoreTarget = backup;
                    this.restoreConfirmText = '';
                    this.restoreModalOpen = true;
                },
                openDelete(backup) {
                    this.deleteTarget = backup;
                    this.deleteModalOpen = true;
                },
            }
        }
    </script>

@endsection
