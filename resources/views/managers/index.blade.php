@extends('layouts.app')
@section('title', 'Manager Assignments')
@section('page_title', 'Manager Assignments')
@section('page_header', 'Manager Assignments')
@section('page_subtitle', 'Assign employees to managers. Each employee can belong to only one manager.')

@section('content')
    <div x-data="managerAssignments()" class="space-y-6">

        {{-- Validation errors --}}
        @if ($errors->any())
            <div class="rounded-lg bg-rose-50 border border-rose-200 px-4 py-3 text-sm text-rose-700">
                {{ $errors->first() }}
            </div>
        @endif

        {{-- Manager cards grid --}}
        @forelse ($managers as $manager)
            @php $assigned = $manager->assignedEmployees; @endphp
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">

                {{-- Card header --}}
                <div class="flex items-center justify-between px-5 py-4 border-b border-slate-100">
                    <div class="flex items-center gap-3">
                        <div class="h-10 w-10 rounded-full bg-amber-500 text-white flex items-center justify-center text-sm font-bold shrink-0">
                            {{ strtoupper(mb_substr($manager->name, 0, 1)) }}
                        </div>
                        <div>
                            <p class="font-semibold text-slate-800 text-sm">{{ $manager->name }}</p>
                            <p class="text-xs text-slate-500">{{ $manager->email }}</p>
                        </div>
                        <span class="ml-2 inline-flex px-2 py-0.5 rounded-full text-xs bg-amber-50 text-amber-700">Manager</span>
                    </div>

                    <div class="flex items-center gap-3">
                        <span class="text-xs text-slate-500">
                            <span class="font-semibold text-slate-700">{{ $assigned->count() }}</span>
                            {{ Str::plural('employee', $assigned->count()) }} assigned
                        </span>
                        <button type="button"
                            @click="openModal({{ $manager->id }}, '{{ addslashes($manager->name) }}', {{ $assigned->pluck('id') }})"
                            class="inline-flex items-center px-3 py-1.5 rounded-lg text-sm font-medium bg-slate-900 text-white hover:bg-slate-800 transition">
                            Manage Team
                        </button>
                    </div>
                </div>

                {{-- Assigned employee chips --}}
                <div class="px-5 py-3 flex flex-wrap gap-2 min-h-[48px]">
                    @forelse ($assigned as $emp)
                        <div class="flex items-center gap-1.5 bg-slate-100 rounded-full pl-3 pr-1 py-1 text-xs text-slate-700">
                            <span class="font-medium">{{ $emp->name }}</span>
                            <span class="text-slate-400">·</span>
                            <span class="text-slate-500">{{ $emp->employee_code }}</span>
                            <form action="{{ route('managers.unassign', [$manager->id, $emp->id]) }}" method="POST" class="inline"
                                onsubmit="return confirm('Remove {{ addslashes($emp->name) }} from {{ addslashes($manager->name) }}\'s team?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit"
                                    class="ml-0.5 w-5 h-5 rounded-full flex items-center justify-center text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition">
                                    ✕
                                </button>
                            </form>
                        </div>
                    @empty
                        <span class="text-xs text-slate-400 italic self-center">No employees assigned yet.</span>
                    @endforelse
                </div>
            </div>
        @empty
            <div class="bg-white rounded-xl border border-slate-200 px-6 py-12 text-center">
                <p class="text-sm text-slate-500">No managers found.</p>
                <p class="mt-1 text-xs text-slate-400">Go to Employees, edit an employee, and use "Grant manager login access" to create a manager account.</p>
                <a href="{{ route('employees.index') }}" class="mt-3 inline-flex items-center text-sm font-medium text-slate-700 hover:underline">
                    Go to Employees →
                </a>
            </div>
        @endforelse

        {{-- Assignment Modal --}}
        <div x-show="open" x-cloak x-transition.opacity style="margin-top:0"
            class="fixed inset-0 z-40 flex items-start justify-center bg-black/40 pt-16 px-4">
            <div @click.away="open = false"
                class="bg-white rounded-2xl shadow-2xl w-full max-w-lg flex flex-col max-h-[80vh]">

                {{-- Modal header --}}
                <div class="flex items-start justify-between px-6 pt-6 pb-4 border-b border-slate-100 shrink-0">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-900">Assign employees</h2>
                        <p class="mt-0.5 text-xs text-slate-500">Managing team for <span class="font-medium text-slate-700" x-text="managerName"></span></p>
                    </div>
                    <button type="button" @click="open = false"
                        class="rounded-full w-8 h-8 text-slate-400 hover:text-slate-600 hover:bg-slate-100 inline-flex items-center justify-center">✕</button>
                </div>

                {{-- Search --}}
                <div class="px-6 py-3 border-b border-slate-100 shrink-0">
                    <input type="text" x-model="search" placeholder="Search employees…"
                        class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:ring-2 focus:ring-slate-500/60 outline-none">
                </div>

                {{-- Form + scrollable employee list --}}
                <form :action="formAction" method="POST" class="flex flex-col flex-1 min-h-0">
                    @csrf

                    <div class="overflow-y-auto flex-1 px-6 py-3 space-y-1">
                        @foreach ($allEmployees as $emp)
                            @php
                                $assignedTo = $assignmentMap->get($emp->id);
                            @endphp
                            <label
                                x-show="search === '' || '{{ strtolower($emp->name . ' ' . $emp->employee_code . ' ' . $emp->department) }}'.includes(search.toLowerCase())"
                                :class="isOtherManager({{ $emp->id }}) ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer hover:bg-slate-50'"
                                class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition select-none">

                                <input type="checkbox" name="employee_ids[]" value="{{ $emp->id }}"
                                    :checked="isSelected({{ $emp->id }})"
                                    :disabled="isOtherManager({{ $emp->id }})"
                                    @change="toggle({{ $emp->id }})"
                                    class="rounded border-slate-300 text-slate-900 focus:ring-slate-500">

                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium text-slate-800 truncate">{{ $emp->name }}</p>
                                    <p class="text-xs text-slate-500">{{ $emp->employee_code }}
                                        @if ($emp->department) · {{ $emp->department }} @endif
                                        @php $typeBadge = $emp->type === 'daily_rate' ? 'Daily' : 'Hourly'; @endphp
                                        · <span class="text-slate-400">{{ $typeBadge }}</span>
                                    </p>
                                </div>

                                @if ($assignedTo && $assignedTo->manager_id != null)
                                    <span class="text-[10px] text-slate-400 shrink-0"
                                        x-show="isOtherManager({{ $emp->id }})">
                                        {{ $assignedTo->manager_name }}
                                    </span>
                                @endif
                            </label>
                        @endforeach
                    </div>

                    {{-- Footer --}}
                    <div class="px-6 py-4 border-t border-slate-100 flex items-center justify-between shrink-0">
                        <span class="text-xs text-slate-500">
                            <span x-text="selectedIds.length"></span> selected
                        </span>
                        <div class="flex gap-3">
                            <button type="button" @click="open = false"
                                class="px-4 py-2 rounded-lg border border-slate-200 text-sm text-slate-700 hover:bg-slate-50">Cancel</button>
                            <button type="submit"
                                class="px-4 py-2 rounded-lg bg-slate-900 text-sm font-semibold text-white hover:bg-slate-800">Save team</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <script>
            // employee_id → manager_id map for conflict detection
            const assignmentMap = @json(
                $assignmentMap->map(fn($r) => $r->manager_id)
            );

            function managerAssignments() {
                return {
                    open: false,
                    managerId: null,
                    managerName: '',
                    selectedIds: [],
                    search: '',

                    get formAction() {
                        return `/managers/${this.managerId}/assign`;
                    },

                    openModal(managerId, managerName, assignedIds) {
                        this.managerId   = managerId;
                        this.managerName = managerName;
                        this.selectedIds = assignedIds.slice();
                        this.search      = '';
                        this.open        = true;
                    },

                    isSelected(empId) {
                        return this.selectedIds.includes(empId);
                    },

                    isOtherManager(empId) {
                        const assignedTo = assignmentMap[empId];
                        return assignedTo !== undefined && assignedTo !== this.managerId;
                    },

                    toggle(empId) {
                        if (this.isSelected(empId)) {
                            this.selectedIds = this.selectedIds.filter(id => id !== empId);
                        } else {
                            this.selectedIds.push(empId);
                        }
                    },
                };
            }
        </script>
    </div>
@endsection
