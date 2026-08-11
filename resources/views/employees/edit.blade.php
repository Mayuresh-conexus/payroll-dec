@extends('layouts.app')

@section('title', 'Edit ' . $employee->name)
@section('page_title', 'Edit Employee')
@section('page_header', 'Edit ' . $employee->name)
@section('page_subtitle', $employee->employee_code . ($employee->department ? ' · ' . $employee->department : ''))
@section('page_action')
    <div class="flex items-center gap-3">
        {{-- Status is its own action, not a form field — dispatched to the content
             scope because the header renders in a separate yield. --}}
        <button type="button" @click="$dispatch('open-status-modal')"
            class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border text-sm font-medium transition
                {{ $employee->is_active
                    ? 'border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100'
                    : 'border-rose-200 bg-rose-50 text-rose-700 hover:bg-rose-100' }}">
            <span class="w-1.5 h-1.5 rounded-full {{ $employee->is_active ? 'bg-emerald-500' : 'bg-rose-400' }}"></span>
            {{ $employee->is_active ? 'Active' : 'Inactive' }}
        </button>

        <button type="button" @click="$dispatch('open-manager-modal')"
            class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border text-sm font-medium transition
                {{ $employee->user
                    ? 'border-amber-200 bg-amber-50 text-amber-700 hover:bg-amber-100'
                    : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50' }}">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
            </svg>
            {{ $employee->user ? 'Manager' : 'No Manager Access' }}
        </button>

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

    @php
        // Rate fields relevant to this employee's (immutable) pay type, plus the
        // newest existing effective_from per type — the confirm dialog uses these to
        // warn when a chosen date would land behind an already-recorded rate.
        $rateFieldMeta = $employee->type === 'daily_rate'
            ? ['daily_rate' => ['label' => 'Daily Rate', 'unit' => '€', 'decimals' => 2]]
            : [
                'hourly_rate' => ['label' => 'Hourly Rate', 'unit' => '€', 'decimals' => 2],
                'hours_per_day' => ['label' => 'Hours / Day', 'unit' => '', 'decimals' => 1],
            ];

        $rateFields = [];
        $rateInputs = [];
        foreach ($rateFieldMeta as $rateKey => $meta) {
            $latestEntry = ($ratesByType->get($rateKey) ?? collect())->first();
            $rateFields[$rateKey] = $meta + [
                'original' => (float) ($employee->{$rateKey} ?? 0),
                'latestEffective' => $latestEntry && $latestEntry->effective_from
                    ? \Carbon\Carbon::parse($latestEntry->effective_from)->toDateString()
                    : null,
            ];
            $rateInputs[$rateKey] = (string) old($rateKey, $employee->{$rateKey});
        }
    @endphp

    {{-- Single Alpine scope wrapping the form, the history panel AND the modals —
         the modals must live inside it or their x-show bindings resolve nothing. --}}
    <div x-data="employeeEditPage(@js([
        'today' => now()->toDateString(),
        'effectiveFrom' => old('rate_effective_from', now()->toDateString()),
        'rateFields' => $rateFields,
        'rateInputs' => $rateInputs,
        'isActive' => (bool) $employee->is_active,
        'assignedEmployeeIds' => $assignedEmployeeIds,
    ]))"
        @open-status-modal.window="openStatusModal()"
        @open-manager-modal.window="managerModalOpen = true">

        @if ($errors->any())
            <div class="mb-6 rounded-lg bg-rose-50 border border-rose-200 px-4 py-3 text-sm text-rose-700 space-y-1">
                @foreach ($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">

            <div class="lg:col-span-7">
                @include('employees.partials._edit_form')
            </div>

            <div class="lg:col-span-5">
                @include('employees.partials._rate_history_tabs')
            </div>

        </div>

        @include('employees.partials._rate_confirm_modal')
        @include('employees.partials._rate_delete_modal')
        @include('employees.partials._status_modal')
        @include('employees.partials._manager_access_modal')
    </div>

    <script>
        function employeeEditPage(config) {
            return {
                /* ── delete a rate history entry ─────────────────────────── */
                deleteModalOpen: false,
                deleteTarget: null,
                deleteConfirmText: '',

                /* ── activate / deactivate ───────────────────────────────── */
                isActive: config.isActive,
                statusModalOpen: false,
                statusEffectiveFrom: config.today,

                openStatusModal() {
                    this.statusEffectiveFrom = this.today;
                    this.statusModalOpen = true;
                },

                /* ── manager login access ────────────────────────────────── */
                managerModalOpen: false,
                managerRevoking: false,
                selectedEmployeeIds: config.assignedEmployeeIds,
                employeeSearch: '',

                toggleEmployee(id) {
                    const i = this.selectedEmployeeIds.indexOf(id);
                    i === -1 ? this.selectedEmployeeIds.push(id) : this.selectedEmployeeIds.splice(i, 1);
                },

                isEmployeeSelected(id) {
                    return this.selectedEmployeeIds.includes(id);
                },

                matchesSearch(name, code) {
                    const q = this.employeeSearch.trim().toLowerCase();
                    return q === '' || name.toLowerCase().includes(q) || code.toLowerCase().includes(q);
                },

                /* ── confirm a rate change before saving ─────────────────── */
                today: config.today,
                effectiveFrom: config.effectiveFrom,
                rateFields: config.rateFields,
                rateInputs: config.rateInputs,
                rateConfirmOpen: false,
                rateConfirmed: false,

                /** Rate fields whose value differs from what is stored. */
                get changedRates() {
                    return Object.entries(this.rateFields).reduce((acc, [key, field]) => {
                        const next = parseFloat(this.rateInputs[key]);
                        if (isNaN(next) || Math.abs(next - field.original) < 0.00001) {
                            return acc;
                        }
                        const delta = next - field.original;
                        acc.push({
                            key,
                            label: field.label,
                            unit: field.unit,
                            decimals: field.decimals,
                            latestEffective: field.latestEffective,
                            from: field.original,
                            to: next,
                            delta,
                            // Percent is undefined when coming from zero.
                            pct: Math.abs(field.original) > 0.00001
                                ? (delta / field.original) * 100
                                : null,
                        });
                        return acc;
                    }, []);
                },

                get hasRateChange() {
                    return this.changedRates.length > 0;
                },

                get isEffectiveToday() {
                    return this.effectiveFrom === this.today;
                },

                get isFutureDated() {
                    return this.effectiveFrom > this.today;
                },

                /**
                 * The newest already-recorded effective date that sits after the chosen
                 * one. If set, this change is backdated and will NOT become current.
                 */
                get blockingDate() {
                    const blockers = this.changedRates
                        .map(rate => rate.latestEffective)
                        .filter(date => date && this.effectiveFrom < date)
                        .sort();
                    return blockers.length ? blockers[blockers.length - 1] : null;
                },

                formatAmount(value, rate) {
                    const num = Number(value).toLocaleString('en-US', {
                        minimumFractionDigits: rate.decimals,
                        maximumFractionDigits: rate.decimals,
                    });
                    return rate.unit ? rate.unit + num : num + ' hrs';
                },

                formatDate(iso) {
                    if (!iso) return '—';
                    const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
                    const [year, month, day] = iso.split('-');
                    return `${day} ${months[parseInt(month, 10) - 1]} ${year}`;
                },

                onSubmit(event) {
                    // Already confirmed, or nothing rate-related changed — let it through.
                    if (this.rateConfirmed || !this.hasRateChange) {
                        return;
                    }
                    event.preventDefault();
                    this.rateConfirmOpen = true;
                },

                confirmSave() {
                    this.rateConfirmed = true;
                    this.rateConfirmOpen = false;
                    // requestSubmit() (not submit()) keeps native field validation intact.
                    this.$refs.editForm.requestSubmit();
                },
            };
        }
    </script>

@endsection
