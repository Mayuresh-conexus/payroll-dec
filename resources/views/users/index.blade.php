@extends('layouts.app')
@section('title', 'Users')
@section('page_title', 'Users')
@section('page_header', 'User Accounts')
@section('page_subtitle', 'Manage admin and manager accounts.')
@section('page_action')
    <button @click="$dispatch('open-create-user')"
        class="inline-flex items-center px-4 py-2 rounded-lg text-sm font-medium bg-slate-900 text-white hover:bg-slate-800 transition">
        + Add User
    </button>
@endsection

@section('content')
    <div x-data="usersPage()" class="space-y-6"
        @open-create-user.window="openCreate = true"
        x-init="
            @if($errors->any() && old('_modal') === 'create') openCreate = true; @endif
            @if($errors->any() && old('_modal') === 'edit') openEdit = true; @endif
        ">

        @if ($errors->has('delete'))
            <div class="rounded-lg bg-rose-50 border border-rose-200 px-4 py-3 text-sm text-rose-700">
                {{ $errors->first('delete') }}
            </div>
        @endif

        {{-- Table --}}
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-slate-500 uppercase text-xs font-semibold">
                    <tr>
                        <th class="px-4 py-3 text-left">Name</th>
                        <th class="px-4 py-3 text-left">Email</th>
                        <th class="px-4 py-3 text-center">Role</th>
                        <th class="px-4 py-3 text-center">Joined</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($users as $user)
                        <tr class="hover:bg-slate-50/80 {{ $user->id === auth()->id() ? 'bg-brand-50/30' : '' }}">
                            <td class="px-4 py-3 font-medium text-slate-800">
                                {{ $user->name }}
                                @if ($user->id === auth()->id())
                                    <span class="ml-1 text-[10px] bg-brand-100 text-brand-700 px-1.5 py-0.5 rounded-full">You</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-slate-600">{{ $user->email }}</td>
                            <td class="px-4 py-3 text-center">
                                @if ($user->role === 'admin')
                                    <span class="inline-flex px-2 py-1 rounded-full text-xs bg-violet-50 text-violet-700">
                                        Admin
                                    </span>
                                @else
                                    <span class="inline-flex px-2 py-1 rounded-full text-xs bg-sky-50 text-sky-700">
                                        Manager
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-center text-slate-500 text-xs">
                                {{ $user->created_at->format('d M Y') }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex justify-end items-center gap-2 text-slate-500">
                                    {{-- Edit --}}
                                    <button type="button"
                                        @click='openEdit = true; editingUser = @json($user)'
                                        data-tooltip="Edit"
                                        class="p-1.5 rounded-md hover:bg-brand-50 hover:text-brand-600 transition">
                                        <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 3.487a1.5 1.5 0 0 1 2.121 0l1.53 1.53a1.5 1.5 0 0 1 0 2.122l-10.01 10.01-4.243.707.707-4.243 10-10.126Z" />
                                        </svg>
                                    </button>

                                    {{-- Delete (cannot delete self) --}}
                                    @if ($user->id !== auth()->id())
                                        <form action="{{ route('users.destroy', $user->id) }}" method="POST"
                                            class="inline" onsubmit="return confirm('Delete user {{ addslashes($user->name) }}?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" data-tooltip="Delete"
                                                class="p-1.5 rounded-md hover:bg-rose-50 hover:text-rose-600 transition">
                                                <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 7h12M10 11v6m4-6v6M9 4h6v3H9zM4 7h16l-1 13H5L4 7Z" />
                                                </svg>
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-6 text-center text-sm text-slate-500">
                                No users found. Use "Add User" to create one.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            <div class="px-4 py-3 border-t border-slate-100">
                {{ $users->links() }}
            </div>
        </div>

        {{-- Create User Modal --}}
        <div x-show="openCreate" x-cloak x-transition.opacity style="margin-top:0"
            class="fixed inset-0 z-40 flex items-center justify-center bg-black/40">
            <div @click.away="openCreate = false"
                class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-6 space-y-5">
                <div class="flex items-start justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-900">Add user</h2>
                        <p class="mt-1 text-xs text-slate-500">Create a new admin or manager account.</p>
                    </div>
                    <button type="button" @click="openCreate = false"
                        class="rounded-full w-8 h-8 text-slate-400 hover:text-slate-600 hover:bg-slate-100 inline-flex items-center justify-center">✕</button>
                </div>

                @if($errors->any() && old('_modal') === 'create')
                    <div class="rounded-lg bg-rose-50 border border-rose-200 px-3 py-2 text-xs text-rose-700 space-y-1">
                        @foreach($errors->all() as $error)
                            <p>{{ $error }}</p>
                        @endforeach
                    </div>
                @endif

                <form action="{{ route('users.store') }}" method="POST" class="space-y-4">
                    @csrf
                    <input type="hidden" name="_modal" value="create">

                    <div class="grid grid-cols-1 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">Full name <span class="text-rose-500">*</span></label>
                            <input type="text" name="name" required value="{{ old('name') }}"
                                class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:ring-2 focus:ring-slate-500/60 outline-none"
                                placeholder="John Doe">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">Email <span class="text-rose-500">*</span></label>
                            <input type="email" name="email" required value="{{ old('email') }}"
                                class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:ring-2 focus:ring-slate-500/60 outline-none"
                                placeholder="john@example.com">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">Password <span class="text-rose-500">*</span></label>
                            <input type="password" name="password" required minlength="8"
                                class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:ring-2 focus:ring-slate-500/60 outline-none"
                                placeholder="Min. 8 characters">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">Confirm password <span class="text-rose-500">*</span></label>
                            <input type="password" name="password_confirmation" required minlength="8"
                                class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:ring-2 focus:ring-slate-500/60 outline-none"
                                placeholder="Repeat password">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">Role <span class="text-rose-500">*</span></label>
                            <select name="role" required
                                class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:ring-2 focus:ring-slate-500/60 outline-none">
                                <option value="admin" {{ old('role', 'admin') === 'admin' ? 'selected' : '' }}>Admin</option>
                                <option value="manager" {{ old('role') === 'manager' ? 'selected' : '' }}>Manager</option>
                            </select>
                        </div>
                    </div>

                    <div class="flex justify-end gap-3 pt-1">
                        <button type="button" @click="openCreate = false"
                            class="px-4 py-2 rounded-lg border border-slate-200 text-sm text-slate-700 hover:bg-slate-50">Cancel</button>
                        <button type="submit"
                            class="px-4 py-2 rounded-lg bg-slate-900 text-sm font-semibold text-white hover:bg-slate-800">Create user</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Edit User Modal --}}
        <div x-show="openEdit && editingUser" x-cloak style="margin-top:0"
            class="fixed inset-0 z-40 flex items-center justify-center bg-black/40">
            <div @click.away="openEdit = false"
                class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-6 space-y-5">
                <div class="flex items-start justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-900">Edit user</h2>
                        <p class="mt-1 text-xs text-slate-500">Update details or reset password.</p>
                    </div>
                    <button type="button" @click="openEdit = false"
                        class="rounded-full w-8 h-8 text-slate-400 hover:text-slate-600 hover:bg-slate-100 inline-flex items-center justify-center">✕</button>
                </div>

                @if($errors->any() && old('_modal') === 'edit')
                    <div class="rounded-lg bg-rose-50 border border-rose-200 px-3 py-2 text-xs text-rose-700 space-y-1">
                        @foreach($errors->all() as $error)
                            <p>{{ $error }}</p>
                        @endforeach
                    </div>
                @endif

                <form :action="'{{ url('users') }}/' + editingUser.id" method="POST" class="space-y-4">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="_modal" value="edit">

                    <div class="grid grid-cols-1 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">Full name <span class="text-rose-500">*</span></label>
                            <input type="text" name="name" required x-model="editingUser.name"
                                class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:ring-2 focus:ring-slate-500/60 outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">Email <span class="text-rose-500">*</span></label>
                            <input type="email" name="email" required x-model="editingUser.email"
                                class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:ring-2 focus:ring-slate-500/60 outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">Role <span class="text-rose-500">*</span></label>
                            <template x-if="editingUser.id === {{ auth()->id() }}">
                                <div>
                                    <input type="hidden" name="role" value="admin">
                                    <p class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-500">Admin <span class="text-xs">(you can't change your own role)</span></p>
                                </div>
                            </template>
                            <template x-if="editingUser.id !== {{ auth()->id() }}">
                                <select name="role" required x-model="editingUser.role"
                                    class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:ring-2 focus:ring-slate-500/60 outline-none">
                                    <option value="admin">Admin</option>
                                    <option value="manager">Manager</option>
                                </select>
                            </template>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">New password <span class="text-slate-400">(leave blank to keep current)</span></label>
                            <input type="password" name="password" minlength="8"
                                class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:ring-2 focus:ring-slate-500/60 outline-none"
                                placeholder="Leave blank to keep current">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">Confirm new password</label>
                            <input type="password" name="password_confirmation" minlength="8"
                                class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:ring-2 focus:ring-slate-500/60 outline-none"
                                placeholder="Repeat new password">
                        </div>
                    </div>

                    <div class="flex justify-end gap-3 pt-1">
                        <button type="button" @click="openEdit = false"
                            class="px-4 py-2 rounded-lg border border-slate-200 text-sm text-slate-700 hover:bg-slate-50">Cancel</button>
                        <button type="submit"
                            class="px-4 py-2 rounded-lg bg-slate-900 text-sm font-semibold text-white hover:bg-slate-800">Update user</button>
                    </div>
                </form>
            </div>
        </div>

        <script>
            function usersPage() {
                return {
                    openCreate: false,
                    openEdit: false,
                    editingUser: {},
                }
            }
        </script>
    </div>
@endsection
