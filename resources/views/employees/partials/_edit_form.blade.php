{{-- employees/partials/_edit_form.blade.php — Edit employee form (right column) --}}
<div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
    <form method="POST" action="{{ route('employees.update', $employee) }}" class="space-y-6">
        @csrf
        @method('PUT')

        <div class="p-6 space-y-6">

            {{-- Basic details --}}
            <div class="space-y-3">
                <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-500">Basic details</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Employee code <span class="text-rose-500">*</span></label>
                        <input type="text" name="employee_code" required value="{{ old('employee_code', $employee->employee_code) }}"
                            class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:ring-2 focus:ring-slate-500/60 focus:border-slate-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Name <span class="text-rose-500">*</span></label>
                        <input type="text" name="name" required value="{{ old('name', $employee->name) }}"
                            class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:ring-2 focus:ring-slate-500/60 focus:border-slate-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Joining date</label>
                        <input type="date" name="joining_date" value="{{ old('joining_date', $employee->joining_date?->format('Y-m-d')) }}"
                            class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:ring-2 focus:ring-slate-500/60 focus:border-slate-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Department</label>
                        <input type="text" name="department" value="{{ old('department', $employee->department) }}"
                            class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:ring-2 focus:ring-slate-500/60 focus:border-slate-500 outline-none">
                    </div>
                </div>
            </div>

            <hr class="border-slate-100">

            {{-- Pay type and rate --}}
            <div class="space-y-3">
                <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-500">Pay type and rate</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 items-start">
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Type <span class="text-rose-500">*</span></label>
                        <input type="text" value="{{ $employee->type === 'daily_rate' ? 'Daily rate' : 'Hourly' }}" disabled
                            class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm bg-slate-50 text-slate-700">
                        <input type="hidden" name="type" value="{{ $employee->type }}">
                        <p class="mt-1 text-[11px] text-slate-400">Pay type cannot be changed after creation.</p>
                    </div>

                    @if ($employee->type === 'daily_rate')
                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">Daily rate</label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-3 flex items-center text-xs text-slate-400">€</span>
                                <input type="number" step="0.01" name="daily_rate" value="{{ old('daily_rate', $employee->daily_rate) }}"
                                    class="w-full rounded-lg border border-slate-200 pl-7 pr-3 py-2 text-sm focus:ring-2 focus:ring-slate-500/60 focus:border-slate-500 outline-none">
                            </div>
                        </div>
                    @else
                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">Hourly rate</label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-3 flex items-center text-xs text-slate-400">€</span>
                                <input type="number" step="0.01" name="hourly_rate" value="{{ old('hourly_rate', $employee->hourly_rate) }}"
                                    class="w-full rounded-lg border border-slate-200 pl-7 pr-3 py-2 text-sm focus:ring-2 focus:ring-slate-500/60 focus:border-slate-500 outline-none">
                            </div>
                            <div class="mt-2">
                                <label class="block text-xs font-medium text-slate-600 mb-1">Hours per day</label>
                                <input type="number" name="hours_per_day" step="0.25" min="0" value="{{ old('hours_per_day', $employee->hours_per_day) }}"
                                    class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-slate-500/60 focus:border-slate-500 outline-none">
                            </div>
                        </div>
                    @endif
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Bank Transfer Fix Amount</label>
                        <input type="text" name="bank_transfer_fix_amount" value="{{ old('bank_transfer_fix_amount', $employee->bank_transfer_fix_amount) }}"
                            class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:ring-2 focus:ring-slate-500/60 focus:border-slate-500 outline-none"
                            placeholder="Enter amount (optional)">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Weekly Active Days</label>
                        <input type="text" name="weekly_active_days" value="{{ old('weekly_active_days', $employee->weekly_active_days) }}"
                            class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:ring-2 focus:ring-slate-500/60 focus:border-slate-500 outline-none"
                            placeholder="e.g. 6">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">Rate effective from</label>
                    <input type="date" name="rate_effective_from" value="{{ old('rate_effective_from', now()->toDateString()) }}"
                        class="w-full sm:w-64 rounded-lg border border-slate-200 px-3 py-2 text-sm focus:ring-2 focus:ring-slate-500/60 focus:border-slate-500 outline-none">
                    <p class="mt-1 text-[11px] text-slate-400">When updating a rate above, this date controls the effective-from stamped on the new rate history entry.</p>
                </div>
            </div>

            <hr class="border-slate-100">

            {{-- Bank details --}}
            <div class="space-y-3">
                <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                    Bank details <span class="text-slate-400 font-normal normal-case">(optional)</span>
                </h3>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Bank Name</label>
                        <input type="text" name="bank_name" value="{{ old('bank_name', $employee->bank_name) }}"
                            class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:ring-2 focus:ring-slate-500/60 outline-none"
                            placeholder="e.g. SBI">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Account Number</label>
                        <input type="text" name="bank_account" value="{{ old('bank_account', $employee->bank_account) }}"
                            class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:ring-2 focus:ring-slate-500/60 outline-none"
                            placeholder="Account no.">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">IFSC Code</label>
                        <input type="text" name="bank_ifsc" value="{{ old('bank_ifsc', $employee->bank_ifsc) }}"
                            class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:ring-2 focus:ring-slate-500/60 outline-none"
                            placeholder="e.g. SBIN0001234">
                    </div>
                </div>
            </div>

            <hr class="border-slate-100">

            {{-- Manager system access --}}
            <div class="border border-amber-200 rounded-xl p-4 bg-amber-50/40 space-y-3"
                x-data="{ revokeAccess: false, changePassword: false }">

                @if ($employee->user)
                    <div class="space-y-3">
                        <div class="flex items-center gap-2">
                            <span class="text-[10px] font-bold bg-amber-100 text-amber-700 px-1.5 py-0.5 rounded uppercase tracking-wide">Manager</span>
                            <span class="text-xs font-medium text-slate-700">Login:</span>
                            <span class="text-xs text-slate-600 font-mono">{{ $employee->user->email }}</span>
                        </div>
                        <div class="flex flex-wrap gap-3">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" x-model="changePassword"
                                    class="rounded border-slate-300 text-amber-600 focus:ring-amber-500">
                                <span class="text-xs text-slate-600">Change login email / password</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" name="revoke_manager_access" value="1" x-model="revokeAccess"
                                    class="rounded border-slate-300 text-rose-500 focus:ring-rose-400">
                                <span class="text-xs text-rose-600 font-medium">Revoke manager access</span>
                            </label>
                        </div>
                        <div x-show="changePassword && !revokeAccess" x-collapse class="space-y-3">
                            <div>
                                <label class="block text-xs font-medium text-slate-600 mb-1">New login email</label>
                                <input type="email" name="manager_email" autocomplete="off"
                                    :disabled="!changePassword || revokeAccess"
                                    value="{{ old('manager_email', $employee->user->email) }}"
                                    class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:ring-2 focus:ring-amber-400/60 outline-none">
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-medium text-slate-600 mb-1">New password <span class="text-slate-400">(leave blank to keep)</span></label>
                                    <input type="password" name="manager_password" minlength="8" autocomplete="new-password"
                                        :disabled="!changePassword || revokeAccess"
                                        class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:ring-2 focus:ring-amber-400/60 outline-none"
                                        placeholder="Leave blank to keep">
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-slate-600 mb-1">Confirm new password</label>
                                    <input type="password" name="manager_password_confirmation" minlength="8" autocomplete="new-password"
                                        :disabled="!changePassword || revokeAccess"
                                        class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:ring-2 focus:ring-amber-400/60 outline-none">
                                </div>
                            </div>
                        </div>
                    </div>
                @else
                    <div class="space-y-3" x-data="{ grantAccess: false }">
                        <label class="flex items-center gap-2.5 cursor-pointer">
                            <input type="checkbox" name="grant_manager_access" value="1"
                                x-model="grantAccess"
                                class="rounded border-slate-300 text-amber-600 focus:ring-amber-500">
                            <span class="text-xs font-semibold text-slate-700">Grant manager login access</span>
                            <span class="ml-auto text-[10px] font-medium text-amber-700 bg-amber-100 px-1.5 py-0.5 rounded">Manager role</span>
                        </label>
                        <div x-show="grantAccess" x-collapse class="space-y-3 pt-1">
                            <p class="text-[11px] text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">
                                Enter an existing manager's email to link their account, or a new email to create one.
                                Leave password blank when linking an existing account.
                            </p>
                            <div>
                                <label class="block text-xs font-medium text-slate-600 mb-1">Login email <span class="text-rose-500">*</span></label>
                                <input type="email" name="manager_email" autocomplete="off"
                                    :disabled="!grantAccess"
                                    value="{{ old('manager_email') }}"
                                    class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:ring-2 focus:ring-amber-400/60 outline-none"
                                    placeholder="manager@example.com">
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-medium text-slate-600 mb-1">Password <span class="text-slate-400">(new accounts only)</span></label>
                                    <input type="password" name="manager_password" minlength="8" autocomplete="new-password"
                                        :disabled="!grantAccess"
                                        class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:ring-2 focus:ring-amber-400/60 outline-none"
                                        placeholder="Min. 8 characters">
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-slate-600 mb-1">Confirm password</label>
                                    <input type="password" name="manager_password_confirmation" minlength="8" autocomplete="new-password"
                                        :disabled="!grantAccess"
                                        class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:ring-2 focus:ring-amber-400/60 outline-none"
                                        placeholder="Repeat password">
                                </div>
                            </div>
                        </div>
                    </div>
                @endif
            </div>

            {{-- Active status --}}
            <input type="hidden" name="is_active" value="0">
            <label class="inline-flex items-center gap-2 text-xs text-slate-600">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $employee->is_active))
                    class="rounded border-slate-300 text-slate-700 focus:ring-slate-500">
                <span>Employee is active</span>
            </label>
        </div>

        {{-- Footer --}}
        <div class="border-t border-slate-100 p-6 bg-slate-50 flex flex-col sm:flex-row justify-end gap-3">
            <a href="{{ route('employees.index') }}"
                class="inline-flex justify-center px-4 py-2 rounded-lg border border-slate-200 text-sm font-medium text-slate-700 hover:bg-white bg-white sm:bg-transparent">
                Cancel
            </a>
            <button type="submit"
                class="inline-flex justify-center px-4 py-2 rounded-lg bg-slate-900 text-sm font-semibold text-white hover:bg-slate-800">
                Update employee
            </button>
        </div>
    </form>
</div>
