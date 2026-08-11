@extends('layouts.app')

@section('title', 'Edit ' . $employee->name)
@section('page_title', 'Edit Employee')
@section('page_header', 'Edit ' . $employee->name)
@section('page_subtitle', $employee->employee_code . ($employee->department ? ' · ' . $employee->department : ''))
@section('page_action')
    <div class="flex items-center gap-3">
        <a href="{{ route('employees.show', $employee) }}"
            class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg border border-slate-200 text-sm text-slate-700 hover:bg-slate-50 transition">
            View Profile
        </a>
        <a href="{{ route('employees.index') }}"
            class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg border border-slate-200 text-sm text-slate-700 hover:bg-slate-50 transition">
            ← Back to Employees
        </a>
    </div>
@endsection

@section('content')

    @if ($errors->any())
        <div class="mb-6 rounded-lg bg-rose-50 border border-rose-200 px-4 py-3 text-sm text-rose-700 space-y-1">
            @foreach ($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start" x-data="employeeEditPage()">

        <div class="lg:col-span-7">
            @include('employees.partials._edit_form')
        </div>

        <div class="lg:col-span-5">
            @include('employees.partials._rate_history_tabs')
        </div>

    </div>

    @include('employees.partials._rate_delete_modal')

    <script>
        function employeeEditPage() {
            return {
                deleteModalOpen: false,
                deleteTarget: null,
            };
        }
    </script>

@endsection
