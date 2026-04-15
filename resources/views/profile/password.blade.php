@extends('layouts.app')
@section('title', 'Change Password')
@section('page_title', 'Change Password')
@section('page_header', 'Change Password')
@section('page_subtitle', 'Update your account password. Use a strong password of at least 8 characters.')

@section('content')
    <div class="max-w-md mx-auto">
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 space-y-5">
            <div>
                <h1 class="text-lg font-semibold text-slate-900">Change your password</h1>
                <p class="mt-1 text-xs text-slate-500">Your new password must be at least 8 characters.</p>
            </div>

            @if (session('success'))
                <div class="rounded-lg bg-emerald-50 border border-emerald-200 px-4 py-3 text-sm text-emerald-700">
                    {{ session('success') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="rounded-lg bg-rose-50 border border-rose-200 px-4 py-3 text-sm text-rose-700 space-y-1">
                    @foreach ($errors->all() as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            @endif

            <form action="{{ route('profile.password.update') }}" method="POST" class="space-y-4">
                @csrf

                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">Current password <span class="text-rose-500">*</span></label>
                    <input type="password" name="current_password" required autofocus
                        class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:ring-2 focus:ring-slate-500/60 focus:border-slate-500 outline-none"
                        placeholder="Your current password">
                </div>

                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">New password <span class="text-rose-500">*</span></label>
                    <input type="password" name="password" required minlength="8"
                        class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:ring-2 focus:ring-slate-500/60 focus:border-slate-500 outline-none"
                        placeholder="Min. 8 characters">
                </div>

                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">Confirm new password <span class="text-rose-500">*</span></label>
                    <input type="password" name="password_confirmation" required minlength="8"
                        class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:ring-2 focus:ring-slate-500/60 focus:border-slate-500 outline-none"
                        placeholder="Repeat new password">
                </div>

                <div class="flex justify-end gap-3 pt-2">
                    <a href="{{ route('dashboard') }}"
                        class="px-4 py-2 rounded-lg border border-slate-200 text-sm text-slate-700 hover:bg-slate-50">Cancel</a>
                    <button type="submit"
                        class="px-4 py-2 rounded-lg bg-slate-900 text-sm font-semibold text-white hover:bg-slate-800">
                        Change password
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection
