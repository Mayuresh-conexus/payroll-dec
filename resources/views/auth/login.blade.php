@extends('layouts.auth')

@section('title', 'Sign in')

@section('content')

    <div class="bg-white/90 backdrop-blur-xl">

        {{-- <h2 class="text-2xl font-semibold text-slate-800 mb-2">Welcome back</h2> --}}
        <p class="text-sm text-slate-500 mb-6">
            Sign in to access your payroll dashboard.
        </p>

        <form action="{{ route('login.attempt') }}" method="POST" class="space-y-5">
            @csrf

            {{-- Email --}}
            <div>
                <label class="text-sm font-medium text-slate-700 mb-1 block">Email</label>
                <input type="email" name="email" value="{{ old('email') }}" required autofocus
                    class="w-full px-4 py-2.5 rounded-xl border border-slate-300 bg-white text-slate-800
                              focus:ring-2 focus:ring-blue-400 focus:border-blue-400 focus:outline-none
                              transition">
                @error('email')
                    <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                @enderror
            </div>

            {{-- Password --}}
            <div>
                <label class="text-sm font-medium text-slate-700 mb-1 block">Password</label>
                <input type="password" name="password" required
                    class="w-full px-4 py-2.5 rounded-xl border border-slate-300 bg-white text-slate-800
                              focus:ring-2 focus:ring-blue-400 focus:border-blue-400 focus:outline-none
                              transition">
                @error('password')
                    <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                @enderror
            </div>

            {{-- Remember me --}}
            <div class="flex items-center justify-between text-sm text-slate-600">
                <label class="inline-flex items-center gap-2">
                    <input type="checkbox" name="remember"
                        class="rounded border-slate-300 text-blue-500 focus:ring-blue-400"
                        {{ old('remember') ? 'checked' : '' }}>
                    <span>Remember me</span>
                </label>
            </div>

            {{-- Button --}}
            <button
                class="w-full py-3 rounded-xl bg-blue-600 hover:bg-blue-700
                       text-white font-semibold text-sm shadow-md shadow-blue-200
                       transition">
                Sign in
            </button>

        </form>

    </div>

@endsection
