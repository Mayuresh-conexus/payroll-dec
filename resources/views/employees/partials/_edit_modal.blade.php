{{-- _edit_modal.blade.php — Edit employee modal with rate history --}}
<div style="margin-top: 0" x-show="openEdit && editingEmployee" x-cloak
    class="fixed inset-0 z-40 flex items-center justify-center bg-black/40">
    <div @click.away="openEdit = false"
        class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-hidden flex flex-col">

        <div class="flex items-start pr-6 pl-6 pt-6 justify-between gap-4">
            <div>
                <h2 class="text-lg font-semibold text-slate-900">Edit employee</h2>
                <p class="mt-1 text-xs text-slate-500">Update details for this employee.</p>
            </div>
            <button type="button"
                class="inline-flex items-center justify-center rounded-full w-8 h-8 text-slate-400 hover:text-slate-600 hover:bg-slate-100"
                @click="openEdit = false">✕</button>
        </div>

        <form method="POST" :action="baseUpdateUrl + '/' + (editingEmployee ? editingEmployee.id : '')"
            class="space-y-5 flex-1 flex flex-col min-h-0">
            @csrf
            @method('PUT')

            <div class="overflow-auto flex-1 pr-6 pl-6 pb-6 min-h-0">

                {{-- Basic details --}}
                <div class="space-y-3 pb-3">
                    <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-500">Basic details</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">Employee code <span class="text-rose-500">*</span></label>
                            <input type="text" name="employee_code" required
                                class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:ring-2 focus:ring-slate-500/60 focus:border-slate-500 outline-none"
                                x-model="editingEmployee.employee_code">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">Name <span class="text-rose-500">*</span></label>
                            <input type="text" name="name" required
                                class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:ring-2 focus:ring-slate-500/60 focus:border-slate-500 outline-none"
                                x-model="editingEmployee.name">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">Joining date</label>
                            <input type="date" name="joining_date"
                                class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:ring-2 focus:ring-slate-500/60 focus:border-slate-500 outline-none"
                                x-model="editingEmployee.joining_date">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">Department</label>
                            <input type="text" name="department"
                                class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:ring-2 focus:ring-slate-500/60 focus:border-slate-500 outline-none"
                                x-model="editingEmployee.department">
                        </div>
                    </div>
                </div>

                <hr class="border-slate-100 pb-3">

                {{-- Pay type and rate --}}
                <div class="space-y-3">
                    <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-500">Pay type and rate</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 items-start">
                        <div class="sm:col-span-1">
                            <label class="block text-xs font-medium text-slate-600 mb-1">Type <span class="text-rose-500">*</span></label>
                            <div x-show="editingEmployee" class="flex flex-col">
                                <input type="text"
                                    :value="(editingEmployee.type === 'daily_rate' ? 'Daily rate' : 'Hourly')"
                                    disabled
                                    class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm bg-slate-50 text-slate-700">
                                <input type="hidden" name="type" :value="editingEmployee.type">
                            </div>
                            <select name="type" x-show="!editingEmployee" x-model="editingEmployee.type"
                                class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:ring-2 focus:ring-slate-500/60 focus:border-slate-500 outline-none bg-white">
                                <option value="daily_rate">Daily rate</option>
                                <option value="hourly">Hourly</option>
                            </select>
                        </div>

                        <template x-if="editingEmployee && editingEmployee.type === 'daily_rate'">
                            <div>
                                <label class="block text-xs font-medium text-slate-600 mb-1">Daily rate</label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-3 flex items-center text-xs text-slate-400">₹</span>
                                    <input type="number" step="0.01" name="daily_rate"
                                        x-model="editingEmployee.daily_rate"
                                        class="w-full rounded-lg border border-slate-200 pl-7 pr-3 py-2 text-sm focus:ring-2 focus:ring-slate-500/60 focus:border-slate-500 outline-none">
                                </div>
                            </div>
                        </template>

                        <template x-if="editingEmployee && editingEmployee.type === 'hourly'">
                            <div>
                                <label class="block text-xs font-medium text-slate-600 mb-1">Hourly rate</label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-3 flex items-center text-xs text-slate-400">₹</span>
                                    <input type="number" step="0.01" name="hourly_rate"
                                        x-model="editingEmployee.hourly_rate"
                                        class="w-full rounded-lg border border-slate-200 pl-7 pr-3 py-2 text-sm focus:ring-2 focus:ring-slate-500/60 focus:border-slate-500 outline-none">
                                </div>
                                <div class="mt-2">
                                    <label class="block text-xs font-medium text-slate-600 mb-1">Hours per day</label>
                                    <input type="number" name="hours_per_day" step="0.25" min="0"
                                        x-model="editingEmployee.hours_per_day"
                                        class="w-full border rounded px-3 py-2 text-sm">
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 mt-4 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Bank Transfer Fix Amount</label>
                        <input type="text" name="bank_transfer_fix_amount"
                            class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:ring-2 focus:ring-slate-500/60 focus:border-slate-500 outline-none"
                            placeholder="Enter amount (optional)" x-model="editingEmployee.bank_transfer_fix_amount">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Weekly Active Days</label>
                        <input type="text" name="weekly_active_days"
                            class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:ring-2 focus:ring-slate-500/60 focus:border-slate-500 outline-none"
                            placeholder="e.g. 6" x-model="editingEmployee.weekly_active_days">
                    </div>
                </div>

                {{-- Bank details --}}
                <div class="border-t border-slate-100 pt-4 mt-4">
                    <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-3">Bank details <span class="text-slate-400 font-normal">(optional)</span></p>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">Bank Name</label>
                            <input type="text" name="bank_name" x-model="editingEmployee.bank_name"
                                class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:ring-2 focus:ring-slate-500/60 outline-none"
                                placeholder="e.g. SBI">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">Account Number</label>
                            <input type="text" name="bank_account" x-model="editingEmployee.bank_account"
                                class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:ring-2 focus:ring-slate-500/60 outline-none"
                                placeholder="Account no.">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">IFSC Code</label>
                            <input type="text" name="bank_ifsc" x-model="editingEmployee.bank_ifsc"
                                class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:ring-2 focus:ring-slate-500/60 outline-none"
                                placeholder="e.g. SBIN0001234">
                        </div>
                    </div>
                </div>

                <div class="pb-3">
                    <label class="block text-xs mt-4 font-medium text-slate-600 mb-1">Rate effective from</label>
                    <input type="date" name="rate_effective_from"
                        x-model="editingEmployee.rate_effective_from"
                        class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"
                        value="{{ now()->toDateString() }}">
                    <p class="mt-1 text-[11px] text-slate-400">When updating rates, this date controls the effective-from for the recorded rate change.</p>
                </div>

                <hr class="border-slate-100 pb-3">

                {{-- Rate history --}}
                <div class="space-y-3 pb-3">
                    <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-500">Rate history</h3>
                    <div class="text-sm text-slate-700">
                        <template x-if="editingEmployee && editingEmployee.ratesByType">
                            <div class="space-y-4">
                                <template x-for="rateType in ['daily_rate','hourly_rate','hours_per_day']" :key="rateType">
                                    <div>
                                        <div class="flex items-center justify-between">
                                            <h4 class="text-xs font-medium text-slate-600"
                                                x-text="(rateType === 'daily_rate' ? 'Daily rates' : (rateType === 'hourly_rate' ? 'Hourly rates' : 'Hours per day'))">
                                            </h4>
                                            <button type="button" class="text-xs text-slate-500 underline"
                                                @click="toggleMore(rateType)"
                                                x-show="(editingEmployee.ratesByType[rateType] || []).length && ((editingEmployee.rateVisible && editingEmployee.rateVisible[rateType]) < (editingEmployee.ratesByType[rateType] || []).length)">
                                                <span x-text="'Show all'"></span>
                                            </button>
                                        </div>
                                        <div class="mt-2 bg-slate-50 rounded border border-slate-100 p-3">
                                            <template x-if="(editingEmployee.ratesByType[rateType] || []).length">
                                                <table class="w-full text-xs">
                                                    <thead>
                                                        <tr class="text-slate-500 text-left">
                                                            <th class="py-1">Change</th>
                                                            <th class="py-1">Effective</th>
                                                            <th class="py-1">By</th>
                                                            <th class="py-1">When</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <template
                                                            x-for="r in (editingEmployee.ratesByType[rateType] || []).slice(0, (editingEmployee.rateVisible && editingEmployee.rateVisible[rateType]) || 5)"
                                                            :key="r.id">
                                                            <tr>
                                                                <td class="py-1">
                                                                    <span x-text="(r.prev_amount !== null ? Number(r.prev_amount).toFixed(2) + ' → ' : '') + (r.amount !== null ? Number(r.amount).toFixed(2) : '-')"></span>
                                                                    <span class="text-slate-400"
                                                                        x-text="rateType === 'daily_rate' ? ' /day' : (rateType === 'hourly_rate' ? ' /hr' : '')"></span>
                                                                </td>
                                                                <td class="py-1" x-text="r.effective_from ?? '-'"></td>
                                                                <td class="py-1" x-text="r.created_by_name ?? r.created_by ?? '-'"></td>
                                                                <td class="py-1" x-text="r.created_at ?? '-'"></td>
                                                            </tr>
                                                        </template>
                                                    </tbody>
                                                </table>
                                            </template>
                                            <template x-if="!(editingEmployee.ratesByType[rateType] || []).length">
                                                <div class="text-xs text-slate-400">No entries.</div>
                                            </template>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </template>
                        <template x-if="!editingEmployee || !editingEmployee.ratesByType">
                            <div class="text-xs text-slate-400">No rate history available.</div>
                        </template>
                    </div>
                </div>

                {{-- Active status --}}
                <input type="hidden" name="is_active" value="0">
                <label class="inline-flex items-center gap-2 text-xs text-slate-600">
                    <input type="checkbox" name="is_active" value="1"
                        x-bind:checked="editingEmployee && editingEmployee.is_active"
                        class="rounded border-slate-300 text-slate-700 focus:ring-slate-500">
                    <span>Employee is active</span>
                </label>
            </div>

            {{-- Footer --}}
            <div class="flex-shrink-0 border-t border-slate-100 p-6 sm:p-7 bg-white flex flex-col sm:flex-row justify-end gap-3">
                <button type="button" @click="openEdit = false"
                    class="inline-flex justify-center px-4 py-2 rounded-lg border border-slate-200 text-sm font-medium text-slate-700 hover:bg-slate-50">
                    Cancel
                </button>
                <button type="submit"
                    class="inline-flex justify-center px-4 py-2 rounded-lg bg-slate-900 text-sm font-semibold text-white hover:bg-slate-800">
                    Update employee
                </button>
            </div>
        </form>
    </div>
</div>
