<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>@yield('title', 'Payroll Dashboard')</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    {{-- Compiled CSS + JS via Vite (Tailwind v4 + Alpine.js) --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        body { font-family: 'Inter', sans-serif; }

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

        [x-cloak] {
            display: none !important;
        }
    </style>


</head>

<body class="bg-slate-50/50 text-slate-800 antialiased selection:bg-brand-100 selection:text-brand-700">

    <div x-data="{ sidebarCollapsed: false }" class="h-screen flex overflow-hidden">

        {{-- Sidebar --}}
        <aside class="bg-slate-900 border-r border-slate-800 flex flex-col transition-all duration-300 z-40 fixed inset-y-0 left-0 md:relative h-screen shrink-0"
            :class="sidebarCollapsed ? '-translate-x-full md:translate-x-0 md:w-[84px]' : 'translate-x-0 w-[260px]'">
            
            {{-- Close button for mobile --}}
            <button @click="sidebarCollapsed = true" class="md:hidden absolute top-4 right-4 text-slate-400 hover:text-white">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>

            {{-- Logo --}}
            <div class="h-20 px-6 flex items-center gap-3 shrink-0">
                <div class="w-10 h-10 rounded-xl bg-brand-600 flex items-center justify-center text-sm font-bold text-white shrink-0">
                    PA
                </div>
                <div class="text-xl font-semibold tracking-tight text-white" x-show="!sidebarCollapsed" x-transition.opacity.duration.200ms>
                    Payroll
                </div>
            </div>

            {{-- Nav --}}
            <nav class="flex-1 px-4 py-4 space-y-2 overflow-y-auto overflow-x-hidden text-[13.5px] font-medium">
                {{-- Dashboard --}}
                <a href="{{ route('dashboard') }}"
                    class="group flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all duration-200
                      @if (request()->routeIs('dashboard')) bg-brand-600 text-white @else text-slate-400 hover:bg-slate-800 hover:text-white @endif">
                    <svg class="w-5 h-5 shrink-0 transition-transform duration-200 group-hover:scale-105 @if(request()->routeIs('dashboard')) text-white @else text-slate-500 group-hover:text-brand-400 @endif"
                        xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 10.5 12 4l9 6.5M5 10.5V20h5v-5h4v5h5v-9.5" />
                    </svg>
                    <span x-show="!sidebarCollapsed" x-transition.opacity>Dashboard</span>
                </a>

                @if (auth()->check() && in_array(auth()->user()->role, ['admin']))
                    {{-- Employees --}}
                    <a href="{{ route('employees.index') }}"
                        class="group flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all duration-200
                          @if (request()->routeIs('employees.*')) bg-brand-600 text-white @else text-slate-400 hover:bg-slate-800 hover:text-white @endif">
                        <svg class="w-5 h-5 shrink-0 transition-transform duration-200 group-hover:scale-105 @if(request()->routeIs('employees.*')) text-white @else text-slate-500 group-hover:text-brand-400 @endif"
                            fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 20v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2" />
                            <circle cx="9" cy="7" r="4" stroke-linecap="round" stroke-linejoin="round" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M23 20v-2a4 4 0 0 0-3-3.87" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16 3.13a4 4 0 0 1 0 7.75" />
                        </svg>
                        <span x-show="!sidebarCollapsed" x-transition.opacity>Employees</span>
                    </a>

                    {{-- Users --}}
                    <a href="{{ route('users.index') }}"
                        class="group flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all duration-200
                          @if (request()->routeIs('users.*')) bg-brand-600 text-white @else text-slate-400 hover:bg-slate-800 hover:text-white @endif">
                        <svg class="w-5 h-5 shrink-0 transition-transform duration-200 group-hover:scale-105 @if(request()->routeIs('users.*')) text-white @else text-slate-500 group-hover:text-brand-400 @endif"
                            fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 12c2.7 0 4.8-2.1 4.8-4.8S14.7 2.4 12 2.4 7.2 4.5 7.2 7.2 9.3 12 12 12zm0 2.4c-3.2 0-9.6 1.6-9.6 4.8v2.4h19.2v-2.4c0-3.2-6.4-4.8-9.6-4.8z" />
                        </svg>
                        <span x-show="!sidebarCollapsed" x-transition.opacity>Users</span>
                    </a>

                    {{-- Bank Holidays --}}
                    <a href="{{ route('holidays.index') }}"
                        class="group flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all duration-200
                          @if (request()->routeIs('holidays.*')) bg-brand-600 text-white @else text-slate-400 hover:bg-slate-800 hover:text-white @endif">
                        <svg class="w-5 h-5 shrink-0 transition-transform duration-200 group-hover:scale-105 @if(request()->routeIs('holidays.*')) text-white @else text-slate-500 group-hover:text-brand-400 @endif"
                            fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0V11.25a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                        </svg>
                        <span x-show="!sidebarCollapsed" x-transition.opacity>Holidays</span>
                    </a>

                    {{-- Leave --}}
                    <a href="{{ route('leaves.index') }}"
                        class="group flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all duration-200
                          @if (request()->routeIs('leaves.*')) bg-brand-600 text-white @else text-slate-400 hover:bg-slate-800 hover:text-white @endif">
                        <svg class="w-5 h-5 shrink-0 transition-transform duration-200 group-hover:scale-105 @if(request()->routeIs('leaves.*')) text-white @else text-slate-500 group-hover:text-brand-400 @endif"
                            fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 2.25v3M16 2.25v3M3.75 9h16.5M4.5 5.25h15a.75.75 0 0 1 .75.75v13.5a.75.75 0 0 1-.75.75h-15a.75.75 0 0 1-.75-.75V6a.75.75 0 0 1 .75-.75Z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="m9.75 14.25 1.5 1.5 3-3.75" />
                        </svg>
                        <span x-show="!sidebarCollapsed" x-transition.opacity>Leave</span>
                    </a>

                    {{-- Manager Assignments --}}
                    <a href="{{ route('managers.index') }}"
                        class="group flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all duration-200
                          @if (request()->routeIs('managers.*')) bg-brand-600 text-white @else text-slate-400 hover:bg-slate-800 hover:text-white @endif">
                        <svg class="w-5 h-5 shrink-0 transition-transform duration-200 group-hover:scale-105 @if(request()->routeIs('managers.*')) text-white @else text-slate-500 group-hover:text-brand-400 @endif"
                            fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M18 21a6 6 0 0 0-12 0" />
                            <circle cx="12" cy="11" r="4" stroke-linecap="round" stroke-linejoin="round" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M22 21a8 8 0 0 0-6-7.7M2 21a8 8 0 0 1 6-7.7" />
                        </svg>
                        <span x-show="!sidebarCollapsed" x-transition.opacity>Assignments</span>
                    </a>

                    {{-- Backups --}}
                    <a href="{{ route('backups.index') }}"
                        class="group flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all duration-200
                          @if (request()->routeIs('backups.*')) bg-brand-600 text-white @else text-slate-400 hover:bg-slate-800 hover:text-white @endif">
                        <svg class="w-5 h-5 shrink-0 transition-transform duration-200 group-hover:scale-105 @if(request()->routeIs('backups.*')) text-white @else text-slate-500 group-hover:text-brand-400 @endif"
                            fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 6.375c0 2.07-3.694 3.75-8.25 3.75s-8.25-1.68-8.25-3.75S7.444 2.625 12 2.625s8.25 1.68 8.25 3.75Z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.375v5.25C3.75 13.694 7.444 15.375 12 15.375s8.25-1.68 8.25-3.75v-5.25M3.75 11.625v5.25c0 2.07 3.694 3.75 8.25 3.75s8.25-1.68 8.25-3.75v-5.25" />
                        </svg>
                        <span x-show="!sidebarCollapsed" x-transition.opacity>Backups</span>
                    </a>
                @endif

                {{-- Attendance --}}
                <a href="{{ route('attendance.index') }}"
                    class="group flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all duration-200
                      @if (request()->routeIs('attendance.*')) bg-brand-600 text-white @else text-slate-400 hover:bg-slate-800 hover:text-white @endif">
                    <svg class="w-5 h-5 shrink-0 transition-transform duration-200 group-hover:scale-105 @if(request()->routeIs('attendance.*')) text-white @else text-slate-500 group-hover:text-brand-400 @endif"
                        xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 3v3M16 3v3M4 10h16M5 5h14a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1z" />
                    </svg>
                    <span x-show="!sidebarCollapsed" x-transition.opacity>Attendance</span>
                </a>

                {{-- Payroll --}}
                @if (auth()->check() && in_array(auth()->user()->role, ['admin']))
                    <div x-data="{ open: {{ request()->routeIs('payroll.*') ? 'true' : 'false' }} }" class="space-y-1 relative">
                        <button type="button" @click="sidebarCollapsed ? (sidebarCollapsed = false, open = true) : open = !open"
                            class="w-full group flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all duration-200
                            {{ request()->routeIs('payroll.*') ? 'bg-brand-600 text-white' : 'text-slate-400 hover:bg-slate-800 hover:text-white' }}">
                            <svg class="w-5 h-5 shrink-0 transition-transform duration-200 group-hover:scale-105 @if(request()->routeIs('payroll.*')) text-white @else text-slate-500 group-hover:text-brand-400 @endif"
                                xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16a2 2 0 0 1 2 2v6a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V9a2 2 0 0 1 2-2Z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M7 9.5h.01M17 9.5h.01M12 12a2 2 0 1 0 0 4 2 2 0 0 0 0-4Z" />
                            </svg>

                            <span x-show="!sidebarCollapsed" x-transition.opacity class="flex-1 text-left">Payroll</span>
                            <span x-show="!sidebarCollapsed" class="ml-auto">
                                <svg :class="{'rotate-180': open}" class="w-4 h-4 transition-transform duration-200 {{ request()->routeIs('payroll.*') ? 'text-white' : 'text-slate-500 group-hover:text-white' }}" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                                </svg>
                            </span>
                        </button>

                        <div x-show="open && !sidebarCollapsed" x-collapse class="ml-[42px] space-y-1">
                            <a href="{{ route('payroll.index') }}" class="block px-3 py-2 rounded-lg text-xs font-semibold transition-colors duration-150 {{ request()->routeIs('payroll.index') ? 'text-brand-400 bg-slate-800' : 'text-slate-500 hover:text-white hover:bg-slate-800' }}">
                                Weekly payroll
                            </a>
                            <a href="{{ route('payroll.monthly.index') }}" class="block px-3 py-2 rounded-lg text-xs font-semibold transition-colors duration-150 {{ request()->routeIs('payroll.monthly.*') ? 'text-brand-400 bg-slate-800' : 'text-slate-500 hover:text-white hover:bg-slate-800' }}">
                                Monthly payroll
                            </a>
                        </div>
                    </div>
                @endif
            </nav>


        </aside>

        {{-- Mobile Overlay --}}
        <div x-show="!sidebarCollapsed" @click="sidebarCollapsed = true" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-30 md:hidden" x-transition.opacity></div>

        {{-- Main content wrapper --}}
        <div class="flex-1 flex flex-col min-w-0 h-screen overflow-y-auto">

            {{-- Top Header / Glassmorphic --}}
            <header class="bg-white/70 backdrop-blur-xl border-b border-white/20 shadow-[0_2px_10px_-3px_rgba(0,0,0,0.02)] flex items-center justify-between px-6 lg:px-8 py-5 sm:py-6 sticky top-0 z-20 transition-all duration-300">
                <div class="flex items-center gap-4">
                    {{-- Hamburger --}}
                    <button type="button" class="inline-flex items-center justify-center w-10 h-10 rounded-xl bg-white border border-slate-200 shadow-sm text-slate-500 hover:text-slate-800 hover:shadow transition" @click="sidebarCollapsed = !sidebarCollapsed">
                        <svg x-show="!sidebarCollapsed" xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" /></svg>
                        <svg x-show="sidebarCollapsed" xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" /></svg>
                    </button>
                    
                    <div>
                        <h1 class="text-xl font-bold tracking-tight text-slate-800">
                            @yield('page_title', 'Dashboard')
                        </h1>
                        <p class="text-[11px] text-slate-500 font-medium uppercase tracking-wider">Payroll System</p>
                    </div>
                </div>

                {{-- Right side: Profile & Actions --}}
                <div class="flex items-center gap-4">
                    <div class="hidden sm:flex items-center gap-2 pr-4 border-r border-slate-200">
                        <a href="{{ route('profile.password') }}" class="p-2 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition" title="Security">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 0 1 3 3m3 0a6 6 0 0 1-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1 1 21.75 8.25Z" /></svg>
                        </a>
                        <form action="{{ route('logout') }}" method="POST">
                            @csrf
                            <button type="submit" class="p-2 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition" title="Logout">
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 8.25L19.5 12m0 0l-3.75 3.75M19.5 12h-9" /><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 4.5h6A2.25 2.25 0 0 1 12.75 6.75v10.5A2.25 2.25 0 0 1 10.5 19.5h-6A2.25 2.25 0 0 1 2.25 17.25V6.75A2.25 2.25 0 0 1 4.5 4.5Z" /></svg>
                            </button>
                        </form>
                    </div>

                    <div class="flex items-center gap-3">
                        <div class="hidden sm:block text-right">
                            <p class="text-[13px] font-bold text-slate-800">{{ auth()->user()->name ?? 'Admin' }}</p>
                            <p class="text-[11px] text-slate-500 font-medium capitalize">{{ auth()->user()->role ?? 'User' }}</p>
                        </div>
                        <div class="h-10 w-10 rounded-full bg-brand-600 text-white flex items-center justify-center text-sm font-bold border-2 border-white ring-1 ring-slate-100">
                            {{ strtoupper(mb_substr(auth()->user()->name ?? 'A', 0, 1)) }}
                        </div>
                    </div>
                </div>
            </header>

            {{-- Page body --}}
            <main class="flex-1 px-4 sm:px-6 md:px-8 lg:px-10 py-6 sm:py-8">

                {{-- Premium, Distinct Page Header Block --}}
                @if($__env->hasSection('page_header'))
                    <div class="bg-white rounded-2xl border border-slate-200/70 shadow-[0_2px_15px_-3px_rgba(0,0,0,0.03)] p-5 sm:p-6 mb-8 flex flex-col xl:flex-row xl:items-center justify-between gap-6 relative overflow-hidden">
                        {{-- Subtle background decoration --}}
                        <div class="absolute top-0 right-0 -mt-4 -mr-4 w-24 h-24 bg-brand-500/5 rounded-full blur-xl pointer-events-none"></div>
                        
                        <div class="relative z-10 flex-1 min-w-0">
                            <h2 class="text-xl sm:text-2xl font-bold tracking-tight text-slate-900 truncate">@yield('page_header')</h2>
                            @if($__env->hasSection('page_subtitle'))
                                <p class="text-sm font-medium text-slate-500/90 mt-1.5 leading-snug">@yield('page_subtitle')</p>
                            @endif
                        </div>
                        @if($__env->hasSection('page_action'))
                            <div class="relative z-10 w-full xl:w-auto xl:flex-shrink-0">
                                @yield('page_action')
                            </div>
                        @endif
                    </div>
                @endif

                @if (session('success'))
                    <div class="mb-4 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 text-sm">
                        {{ session('success') }}
                    </div>
                @endif

                @yield('content')
            </main>
        </div>

    </div>

</body>

</html>
