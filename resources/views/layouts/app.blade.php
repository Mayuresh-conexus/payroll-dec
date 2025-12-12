<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>@yield('title', 'Payroll Dashboard')</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    {{-- Tailwind via CDN --}}
    <script src="https://cdn.tailwindcss.com"></script>

    {{-- Alpine.js --}}
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>

    <style>
        [data-tooltip] {
            position: relative;
        }

        [data-tooltip]:hover::after {
            content: attr(data-tooltip);
            position: absolute;
            top: -32px;
            right: 0;
            background: #1e293b;
            color: white;
            font-size: 11px;
            padding: 3px 6px;
            border-radius: 4px;
            white-space: nowrap;
        }
    </style>


</head>

<body class="bg-slate-100 text-slate-900">

    <div x-data="{ sidebarCollapsed: false }" class="min-h-screen flex">

        {{-- Sidebar --}}
        <aside class="bg-slate-900 text-slate-100 flex flex-col transition-all duration-200"
            :class="sidebarCollapsed ? 'w-20' : 'w-64'">
            {{-- Logo --}}
            <div class="px-4 py-4 border-b border-slate-800 flex items-center gap-2">
                <div class="w-8 h-8 rounded-xl bg-slate-800 flex items-center justify-center text-sm font-bold">
                    PA
                </div>
                <div class="text-lg font-bold tracking-tight" x-show="!sidebarCollapsed" x-transition.opacity>
                    Payroll App
                </div>
            </div>

            {{-- Nav --}}
            <nav class="flex-1 px-2 py-4 space-y-1 text-sm">
                {{-- Dashboard --}}
                <a href="{{ route('dashboard') }}"
                    class="group flex items-center gap-3 px-3 py-2 rounded-lg
                      hover:bg-slate-800 hover:text-white
                      transition-colors duration-150
                      @if (request()->routeIs('dashboard')) bg-slate-800 text-white @else text-slate-200 @endif">
                    {{-- Icon: Home --}}
                    <svg class="w-5 h-5 flex-shrink-0 opacity-80 group-hover:scale-105 transition-transform duration-150"
                        xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                        stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M3 10.5 12 4l9 6.5M5 10.5V20h5v-5h4v5h5v-9.5" />
                    </svg>

                    <span x-show="!sidebarCollapsed" x-transition.opacity>
                        Dashboard
                    </span>
                </a>

                {{-- Employees --}}
                <a href="{{ route('employees.index') }}"
                    class="group flex items-center gap-3 px-3 py-2 rounded-lg
                      hover:bg-slate-800 hover:text-white
                      transition-colors duration-150
                      @if (request()->routeIs('employees.*')) bg-slate-800 text-white @else text-slate-200 @endif">
                    {{-- Icon: Users --}}
                    <svg class="w-5 h-5 flex-shrink-0 opacity-80 group-hover:scale-105 transition-transform duration-150"
                        fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"
                        xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M17 20v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2" />
                        <circle cx="9" cy="7" r="4" stroke-linecap="round" stroke-linejoin="round" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M23 20v-2a4 4 0 0 0-3-3.87" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16 3.13a4 4 0 0 1 0 7.75" />
                    </svg>


                    <span x-show="!sidebarCollapsed" x-transition.opacity>
                        Employees
                    </span>
                </a>

                {{-- Attendance (static for now) --}}
                <a href="{{ route('attendance.index') }}"
                    class="group flex items-center gap-3 px-3 py-2 rounded-lg
                      hover:bg-slate-800 hover:text-white
                      transition-colors duration-150 text-slate-200">
                    {{-- Icon: Calendar --}}
                    <svg class="w-5 h-5 flex-shrink-0 opacity-80 group-hover:scale-105 transition-transform duration-150"
                        xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                        stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M8 3v3M16 3v3M4 10h16M5 5h14a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1z" />
                    </svg>

                    <span x-show="!sidebarCollapsed" x-transition.opacity>
                        Attendance
                    </span>
                </a>

                {{-- Payroll (static for now) --}}
                <a href="{{ route('payroll.index') }}"
                    class="group flex items-center gap-3 px-3 py-2 rounded-lg
                      hover:bg-slate-800 hover:text-white
                      transition-colors duration-150 text-slate-200">
                    {{-- Icon: Money --}}
                    <svg class="w-5 h-5 flex-shrink-0 opacity-80 group-hover:scale-105 transition-transform duration-150"
                        xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                        stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M4 7h16a2 2 0 0 1 2 2v6a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V9a2 2 0 0 1 2-2Z" />
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M7 9.5h.01M17 9.5h.01M12 12a2 2 0 1 0 0 4 2 2 0 0 0 0-4Z" />
                    </svg>

                    <span x-show="!sidebarCollapsed" x-transition.opacity>
                        Payroll
                    </span>
                </a>

                {{-- Reports (static for now) 
                <a href="#"
                    class="group flex items-center gap-3 px-3 py-2 rounded-lg
                      hover:bg-slate-800 hover:text-white
                      transition-colors duration-150 text-slate-200">
                     Icon: Chart 
                    <svg class="w-5 h-5 flex-shrink-0 opacity-80 group-hover:scale-105 transition-transform duration-150"
                        xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                        stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 19h16M7 16V9M12 16V5M17 16v-7" />
                    </svg>

                    <span x-show="!sidebarCollapsed" x-transition.opacity>
                        Reports
                    </span>
                </a> --}}
            </nav>
        </aside>

        {{-- Main content --}}
        <div class="flex-1 flex flex-col">

            {{-- Top bar --}}
            <header class="h-16 bg-white border-b border-slate-200 flex items-center justify-between px-4 sm:px-6">
                <div class="flex items-center gap-3">
                    {{-- Hamburger for sidebar collapse --}}
                    <button type="button"
                        class="inline-flex items-center justify-center w-9 h-9 rounded-lg border border-slate-200
                           text-slate-600 hover:bg-slate-50 hover:border-slate-300
                           transition-colors duration-150"
                        @click="sidebarCollapsed = !sidebarCollapsed">
                        <svg x-show="!sidebarCollapsed" xmlns="http://www.w3.org/2000/svg" class="w-5 h-5"
                            fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                        <svg x-show="sidebarCollapsed" xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none"
                            viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 6h12M10 12h8M6 18h12" />
                        </svg>
                    </button>

                    <div class="text-lg font-semibold">
                        @yield('page_title', 'Dashboard')
                    </div>
                </div>

                <div class="flex items-center gap-4 text-sm">

                    <div
                        class="flex items-center gap-3 px-3 py-1.5 rounded-full bg-white border border-slate-200 shadow-sm">

                        {{-- Avatar with user icon --}}
                        <div
                            class="h-8 w-8 rounded-full bg-slate-900 text-white flex items-center justify-center text-xs font-semibold">
                            {{ strtoupper(mb_substr(auth()->user()->name ?? 'A', 0, 1)) }}
                        </div>


                        {{-- Name --}}
                        <div class="hidden sm:flex flex-col leading-tight">
                            <span class="text-[11px] uppercase tracking-wide text-slate-400">
                                Logged in
                            </span>
                            <span class="text-sm font-medium text-slate-800 truncate max-w-[150px]">
                                {{ auth()->user()->name ?? 'Admin' }}
                            </span>
                        </div>

                        <div class="sm:hidden">
                            <span class="text-sm font-medium text-slate-800">
                                {{ auth()->user()->name ?? 'Admin' }}
                            </span>
                        </div>

                        {{-- Logout button --}}
                        <form action="{{ route('logout') }}" method="POST">
                            @csrf
                            <button type="submit"
                                class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-100">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none"
                                    xmlns="http://www.w3.org/2000/svg" stroke="currentColor" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M15.75 8.25L19.5 12m0 0l-3.75 3.75M19.5 12h-9" />
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M4.5 4.5h6A2.25 2.25 0 0 1 12.75 6.75v10.5A2.25 2.25 0 0 1 10.5 19.5h-6A2.25 2.25 0 0 1 2.25 17.25V6.75A2.25 2.25 0 0 1 4.5 4.5Z" />
                                </svg>
                                <span>Logout</span>
                            </button>

                        </form>


                    </div>

                </div>

            </header>

            {{-- Page body --}}
            <main class="flex-1 p-4 sm:p-6">
                @if (session('success'))
                    <div
                        class="mb-4 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 text-sm">
                        {{ session('success') }}
                    </div>
                @endif

                @yield('content')
            </main>
        </div>

    </div>

</body>

</html>
