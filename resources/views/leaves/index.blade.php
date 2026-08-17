@extends('layouts.app')

@section('title', 'Leave')
@section('page_title', 'Leave')
@section('page_header', 'Employee Leave')
@section('page_subtitle', 'Paid time off. Hourly staff accrue 8% of the hours they clock; daily staff earn four weeks of their own working week.')

@section('page_action')
    <button @click="$dispatch('open-create-leave')"
        class="inline-flex items-center px-4 py-2 rounded-lg text-sm font-medium bg-slate-900 text-white hover:bg-slate-800 transition">
        + Record Leave
    </button>
@endsection

@section('content')

    @php
        // Only what the modals need to react to the chosen employee: whether to ask
        // for hours, and what their standard day is.
        $employeeMeta = $employees->map(fn ($employee) => [
            'id' => $employee->id,
            'name' => $employee->name,
            'type' => $employee->type,
            'hours_per_day' => (float) ($employee->hours_per_day ?? 0),
        ])->values();
    @endphp

    <div x-data="leavesPage(@js($employeeMeta))" class="space-y-6"
        @open-create-leave.window="openCreate = true"
        x-init="
            @if ($errors->any() && old('_modal') === 'create') openCreate = true; @endif
            @if ($errors->any() && old('_modal') === 'edit') openEdit = true; editingLeave = @js(old()); @endif
        ">

        @if ($errors->has('leave'))
            <div class="rounded-lg bg-rose-50 border border-rose-200 px-4 py-3 text-sm text-rose-700">
                {{ $errors->first('leave') }}
            </div>
        @endif

        @include('leaves.partials._balances')

        @include('leaves.partials._table')

        @include('leaves.partials._create_modal')

        @include('leaves.partials._edit_modal')

        @include('leaves.partials._delete_modal')

    </div>

    <script>
        function leavesPage(employees) {
            return {
                employees: employees,
                openCreate: false,
                openEdit: false,
                editingLeave: {},
                deleteModalOpen: false,
                deleteTarget: null,

                /**
                 * Leave entered with only a start date is a single day. Mirroring
                 * the start into the end field keeps that visible in the form rather
                 * than leaving it to be inferred on the server.
                 */
                syncEndDate(target) {
                    if (! target.end_date || target.end_date < target.start_date) {
                        target.end_date = target.start_date;
                    }
                },

                employeeOf(id) {
                    return this.employees.find(e => String(e.id) === String(id)) || null;
                },

                /** Only hourly staff are paid leave by the hour, so only they are asked. */
                isHourly(id) {
                    return this.employeeOf(id)?.type === 'hourly';
                },

                standardHours(id) {
                    return this.employeeOf(id)?.hours_per_day ?? 0;
                },

                openEditModal(leave) {
                    this.editingLeave = Object.assign({}, leave);
                    this.openEdit = true;
                },

                openDeleteModal(leave) {
                    this.deleteTarget = leave;
                    this.deleteModalOpen = true;
                },
            }
        }
    </script>

@endsection
