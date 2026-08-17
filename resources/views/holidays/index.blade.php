@extends('layouts.app')

@section('title', 'Holidays')
@section('page_title', 'Holidays')
@section('page_header', 'Bank Holidays')
@section('page_subtitle', 'Days marked BH in attendance. Anyone who works one is paid double.')

@section('page_action')
    <button @click="$dispatch('open-create-holiday')"
        class="inline-flex items-center px-4 py-2 rounded-lg text-sm font-medium bg-slate-900 text-white hover:bg-slate-800 transition">
        + Add Holiday
    </button>
@endsection

@section('content')

    <div x-data="holidaysPage()" class="space-y-6"
        @open-create-holiday.window="openCreate = true"
        x-init="
            @if ($errors->any() && old('_modal') === 'create') openCreate = true; @endif
            @if ($errors->any() && old('_modal') === 'edit') openEdit = true; editingHoliday = @js(old()); @endif
        ">

        @if ($errors->has('holiday'))
            <div class="rounded-lg bg-rose-50 border border-rose-200 px-4 py-3 text-sm text-rose-700">
                {{ $errors->first('holiday') }}
            </div>
        @endif

        @include('holidays.partials._table')

        @include('holidays.partials._create_modal')

        @include('holidays.partials._edit_modal')

        @include('holidays.partials._delete_modal')

    </div>

    <script>
        function holidaysPage() {
            return {
                openCreate: false,
                openEdit: false,
                editingHoliday: {},
                deleteModalOpen: false,
                deleteTarget: null,

                /**
                 * A holiday entered with only a start date is a single day. Mirroring
                 * the start into the end field keeps that visible in the form rather
                 * than leaving it to be inferred on the server.
                 */
                syncEndDate(target) {
                    if (! target.end_date || target.end_date < target.start_date) {
                        target.end_date = target.start_date;
                    }
                },

                openEditModal(holiday) {
                    this.editingHoliday = Object.assign({}, holiday);
                    this.openEdit = true;
                },

                openDeleteModal(holiday) {
                    this.deleteTarget = holiday;
                    this.deleteModalOpen = true;
                },
            }
        }
    </script>

@endsection
