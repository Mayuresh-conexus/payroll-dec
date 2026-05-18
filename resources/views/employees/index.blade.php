@extends('layouts.app')

@section('title', 'Employees')
@section('page_header', 'Employees')
@section('page_subtitle', 'Manage daily rate and hourly employees in one place.')
@section('page_action')
    <button @click="$dispatch('open-create-employee')"
        class="inline-flex items-center px-4 py-2 rounded-lg text-sm font-medium bg-slate-900 text-white hover:bg-slate-800 transition">
        + Add Employee
    </button>
@endsection

@section('content')

    @include('employees.partials._script')

    <div x-data='employeesData(@json(url("employees")))' class="space-y-6"
        @open-create-employee.window="openCreate = true">

        @include('employees.partials._search_bar')

        @include('employees.partials._table')

        @include('employees.partials._create_modal')

        @include('employees.partials._edit_modal')

    </div>

@endsection
