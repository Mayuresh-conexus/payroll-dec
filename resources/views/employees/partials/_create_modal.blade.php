{{-- _create_modal.blade.php — Add new employee modal --}}
<div x-show="openCreate" x-cloak x-transition.scale style="margin-top: 0"
    class="fixed inset-0 z-40 flex items-center justify-center bg-black/40">
    <div @click.away="openCreate = false"
        class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl p-6 sm:p-7 space-y-6" x-data="{ empType: 'daily_rate' }">

        <div class="flex items-start justify-between">
            <div>
                <h2 class="text-lg font-semibold text-slate-900">Add employee</h2>
                <p class="mt-1 text-xs text-slate-500">Create a new team member and set their pay type and rate.</p>
            </div>
            <button type="button"
                class="inline-flex items-center justify-center rounded-full w-8 h-8 text-slate-400 hover:text-slate-600 hover:bg-slate-100"
                @click="openCreate = false">✕</button>
        </div>

        <form action="{{ route('employees.store') }}" method="post" class="space-y-5">
            @csrf
            {{-- Basic details --}}
            <div class="space-y-3">
                <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-500">Basic details</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Employee code <span class="text-rose-500">*</span></label>
                        <input type="text" name="employee_code" required
                            class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:ring-2 focus:ring-slate-500/60 focus:border-slate-500 outline-none"
                            placeholder="E001, HR-12, etc">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Name <span class="text-rose-500">*</span></label>
                        <input type="text" name="name" required
                            class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:ring-2 focus:ring-slate-500/60 focus:border-slate-500 outline-none"
                            placeholder="Full name">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Joining date</label>
                        <input type="date" name="joining_date"
                            class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:ring-2 focus:ring-slate-500/60 focus:border-slate-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Department</label>
                        <input type="text" name="department"
                            class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:ring-2 focus:ring-slate-500/60 focus:border-slate-500 outline-none"
                            placeholder="Accounts, HR, Production">
                    </div>
                </div>
            </div>

            <hr class="border-slate-100">

            {{-- Pay type and rate --}}
            <div class="space-y-3">
                <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-500">Pay type and rate</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 items-start">
                    <div class="sm:col-span-1">
                        <label class="block text-xs font-medium text-slate-600 mb-1">Type <span class="text-rose-500">*</span></label>
                        <select name="type" x-model="empType"
                            class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:ring-2 focus:ring-slate-500/60 focus:border-slate-500 outline-none bg-white">
                            <option value="daily_rate">Daily rate</option>
                            <option value="hourly">Hourly</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Daily rate</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-3 flex items-center text-xs text-slate-400">₹</span>
                            <input type="number" step="0.01" name="daily_rate"
                                x-bind:disabled="empType !== 'daily_rate'"
                                class="w-full rounded-lg border border-slate-200 pl-7 pr-3 py-2 text-sm focus:ring-2 focus:ring-slate-500/60 focus:border-slate-500 outline-none"
                                placeholder="For staff">
                        </div>
                        <p class="mt-1 text-[11px] text-slate-400" x-show="empType !== 'daily_rate'">Enabled only when type is Daily rate.</p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Hourly rate</label>
                        <div class="relative" x-show="empType === 'hourly'">
                            <span class="absolute inset-y-0 left-3 flex items-center text-xs text-slate-400">₹</span>
                            <input type="number" step="0.01" name="hourly_rate"
                                x-bind:disabled="empType !== 'hourly'"
                                class="w-full rounded-lg border border-slate-200 pl-7 pr-3 py-2 text-sm focus:ring-2 focus:ring-slate-500/60 focus:border-slate-500 outline-none"
                                placeholder="For hourly staff">
                        </div>
                        <p class="mt-1 text-[11px] text-slate-400" x-show="empType !== 'hourly'">Enabled only when type is Hourly.</p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Hours per day</label>
                        <input type="number" name="hours_per_day" step="0.25" min="0"
                            x-bind:disabled="empType !== 'hourly'"
                            class="w-full border rounded px-3 py-2 text-sm" x-show="empType === 'hourly'">
                        <p class="mt-1 text-[11px] text-slate-400" x-show="empType !== 'hourly'">Only used for hourly employees.</p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Bank Transfer Fix Amount</label>
                        <input type="text" name="bank_transfer_fix_amount"
                            class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:ring-2 focus:ring-slate-500/60 focus:border-slate-500 outline-none"
                            placeholder="Enter amount (optional)">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Weekly Active Days</label>
                        <input type="text" name="weekly_active_days"
                            class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:ring-2 focus:ring-slate-500/60 focus:border-slate-500 outline-none"
                            placeholder="e.g. 6">
                    </div>
                </div>
            </div>

            {{-- Bank details --}}
            <div class="border-t border-slate-100 pt-4">
                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-3">Bank details <span class="text-slate-400 font-normal">(optional)</span></p>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Bank Name</label>
                        <input type="text" name="bank_name"
                            class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:ring-2 focus:ring-slate-500/60 outline-none"
                            placeholder="e.g. SBI">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Account Number</label>
                        <input type="text" name="bank_account"
                            class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:ring-2 focus:ring-slate-500/60 outline-none"
                            placeholder="Account no.">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">IFSC Code</label>
                        <input type="text" name="bank_ifsc"
                            class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:ring-2 focus:ring-slate-500/60 outline-none"
                            placeholder="e.g. SBIN0001234">
                    </div>
                </div>
            </div>

            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Rate effective from</label>
                <input type="date" name="rate_effective_from" value="{{ now()->toDateString() }}"
                    class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                <p class="mt-1 text-[11px] text-slate-400">Choose the date when these rates become effective.</p>
            </div>

            <hr class="border-slate-100">

            <div class="flex flex-col sm:flex-row justify-end gap-3 pt-1">
                <button type="button" @click="openCreate = false"
                    class="inline-flex justify-center px-4 py-2 rounded-lg border border-slate-200 text-sm font-medium text-slate-700 hover:bg-slate-50">
                    Cancel
                </button>
                <button type="submit"
                    class="inline-flex justify-center px-4 py-2 rounded-lg bg-slate-900 text-sm font-semibold text-white hover:bg-slate-800">
                    Save employee
                </button>
            </div>
        </form>
    </div>
</div>
