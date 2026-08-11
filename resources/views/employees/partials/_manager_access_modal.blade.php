{{-- employees/partials/_manager_access_modal.blade.php — Manager login + the team they look after --}}
<div x-show="managerModalOpen" x-cloak
    x-transition:enter="ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
    x-transition:leave="ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
    @keydown.escape.window="managerModalOpen = false"
    class="fixed inset-0 z-40 flex items-center justify-center bg-black/40 backdrop-blur-sm">
    <div @click.outside="managerModalOpen = false"
        class="bg-white rounded-xl shadow-xl border border-slate-200 w-full max-w-2xl mx-4 max-h-[90vh] flex flex-col overflow-hidden">

        <div class="flex items-start gap-3 p-5 border-b border-slate-100 shrink-0">
            <div class="flex-shrink-0 w-9 h-9 rounded-full bg-amber-50 border border-amber-200 flex items-center justify-center">
                <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                </svg>
            </div>
            <div>
                <h3 class="text-sm font-semibold text-slate-900">Manager access — {{ $employee->name }}</h3>
                <p class="mt-0.5 text-xs text-slate-500">
                    @if ($managerUser)
                        Logs in as <span class="font-mono">{{ $managerUser->email }}</span>
                    @else
                        Give this employee a login and a team to look after.
                    @endif
                </p>
            </div>
        </div>

        <form action="{{ route('employees.manager-access.update', $employee) }}" method="POST" class="flex-1 flex flex-col min-h-0">
            @csrf
            @method('PUT')
            <input type="hidden" name="revoke" :value="managerRevoking ? 1 : 0">

            <div class="p-5 space-y-5 overflow-y-auto flex-1">

                {{-- Credentials --}}
                <div class="space-y-3" x-show="! managerRevoking">
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Login email <span class="text-rose-500">*</span></label>
                        <input type="email" name="email" autocomplete="off"
                            value="{{ old('email', $managerUser?->email) }}"
                            :disabled="managerRevoking"
                            class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:ring-2 focus:ring-amber-400/60 outline-none"
                            placeholder="manager@example.com">
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">
                                Password
                                @if ($managerUser)
                                    <span class="text-slate-400">(leave blank to keep)</span>
                                @else
                                    <span class="text-rose-500">*</span>
                                @endif
                            </label>
                            <input type="password" name="password" minlength="8" autocomplete="new-password"
                                :disabled="managerRevoking"
                                class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:ring-2 focus:ring-amber-400/60 outline-none"
                                placeholder="Min. 8 characters">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">Confirm password</label>
                            <input type="password" name="password_confirmation" minlength="8" autocomplete="new-password"
                                :disabled="managerRevoking"
                                class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:ring-2 focus:ring-amber-400/60 outline-none"
                                placeholder="Repeat password">
                        </div>
                    </div>
                </div>

                {{-- Team picker: only employees not already claimed by another manager --}}
                <div x-show="! managerRevoking">
                    <div class="flex items-center justify-between gap-3 mb-2">
                        <label class="block text-xs font-medium text-slate-600">
                            Team <span class="text-rose-500">*</span>
                            <span class="text-slate-400 font-normal">(at least one)</span>
                        </label>
                        <span class="text-[11px] text-slate-400">
                            <span x-text="selectedEmployeeIds.length"></span> selected
                        </span>
                    </div>

                    @if ($selectableEmployees->isEmpty())
                        <p class="rounded-lg bg-slate-50 border border-slate-200 px-4 py-3 text-xs text-slate-500">
                            No unassigned employees available — everyone else already reports to a manager.
                        </p>
                    @else
                        <input type="text" x-model="employeeSearch" placeholder="Search name or code…"
                            class="w-full mb-2 rounded-lg border border-slate-200 px-3 py-2 text-sm focus:ring-2 focus:ring-slate-500/60 outline-none">

                        <div class="rounded-lg border border-slate-200 divide-y divide-slate-100 max-h-56 overflow-y-auto">
                            @foreach ($selectableEmployees as $candidate)
                                <label class="flex items-center gap-3 px-3 py-2 hover:bg-slate-50 cursor-pointer"
                                    x-show="matchesSearch(@js($candidate->name), @js($candidate->employee_code))">
                                    <input type="checkbox" name="employee_ids[]" value="{{ $candidate->id }}"
                                        :checked="isEmployeeSelected({{ $candidate->id }})"
                                        @change="toggleEmployee({{ $candidate->id }})"
                                        class="rounded border-slate-300 text-amber-600 focus:ring-amber-500">
                                    <span class="flex-1 min-w-0">
                                        <span class="text-sm text-slate-800">{{ $candidate->name }}</span>
                                        <span class="text-[11px] text-slate-400 font-mono ml-1.5">{{ $candidate->employee_code }}</span>
                                    </span>
                                    @if ($candidate->department)
                                        <span class="text-[11px] text-slate-400 shrink-0">{{ $candidate->department }}</span>
                                    @endif
                                </label>
                            @endforeach
                        </div>
                    @endif
                </div>

                {{-- Revoke --}}
                @if ($managerUser)
                    <div class="border-t border-slate-100 pt-4">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" x-model="managerRevoking"
                                class="rounded border-slate-300 text-rose-500 focus:ring-rose-400">
                            <span class="text-xs font-medium text-rose-600">Revoke manager access</span>
                        </label>
                        <div x-show="managerRevoking" x-collapse>
                            <div class="mt-2 rounded-lg bg-rose-50 border border-rose-100 px-4 py-3 text-xs text-rose-800">
                                The login is deleted and the team is released back to the unassigned pool.
                                The employee record itself is untouched.
                            </div>
                        </div>
                    </div>
                @endif
            </div>

            <div class="flex items-center justify-end gap-2 px-5 py-4 bg-slate-50 border-t border-slate-100 shrink-0">
                <button type="button" @click="managerModalOpen = false"
                    class="px-4 py-2 rounded-lg border border-slate-200 bg-white text-sm font-medium text-slate-700 hover:bg-slate-50 transition">
                    Cancel
                </button>
                <button type="submit"
                    :class="managerRevoking ? 'bg-rose-600 hover:bg-rose-700' : 'bg-slate-900 hover:bg-slate-800'"
                    class="px-4 py-2 rounded-lg text-white text-sm font-medium transition"
                    x-text="managerRevoking ? 'Revoke Access' : '{{ $managerUser ? 'Save Changes' : 'Grant Manager Access' }}'">
                </button>
            </div>
        </form>
    </div>
</div>
