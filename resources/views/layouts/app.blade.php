<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>@yield('title', 'Payroll Dashboard')</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    {{-- Tailwind via CDN --}}
    <script src="https://cdn.tailwindcss.com"></script>

    {{-- Google Fonts: Inter --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    {{-- Alpine.js --}}
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>

    <style>
        body { font-family: 'Inter', sans-serif; }
        [data-tooltip] { position: relative; }
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
        [x-cloak] { display: none !important; }
        
        .sidebar-item-active {
            background: rgba(255, 255, 255, 0.08);
            color: #fff !important;
            border-left: 3px solid #6366f1;
        }
        .sidebar-item-active svg { color: #818cf8 !important; }
        
        .sidebar-item {
            border-left: 3px solid transparent;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .sidebar-item:hover {
            background: rgba(255, 255, 255, 0.04);
            color: #fff !important;
            border-left-color: rgba(99, 102, 241, 0.4);
        }
    </style>
</head>

<body class="bg-slate-50 text-slate-900 antialiased">

    <div x-data="{ sidebarCollapsed: false, mobileSidebarOpen: false }" class="min-h-screen flex text-[15px]">

        {{-- Desktop Sidebar --}}
        <aside class="hidden lg:flex bg-[#0f172a] text-slate-400 flex-col transition-all duration-300 ease-in-out border-r border-slate-800 z-50 shadow-2xl"
            :class="sidebarCollapsed ? 'w-[72px]' : 'w-64'">
            
            {{-- Logo Section --}}
            <div class="h-16 flex items-center px-4 mb-2 border-b border-white/5 overflow-hidden">
            <a href="{{ route('dashboard') }}">  
                <div class="flex items-center gap-3 min-w-0">
                    <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-indigo-500 via-indigo-600 to-indigo-700 flex items-center justify-center text-white shadow-lg shadow-indigo-900/50 flex-shrink-0 ring-1 ring-white/10">  
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5" />
                        </svg>
                    </div>
                    <div class="font-black text-white text-xl whitespace-nowrap" x-show="!sidebarCollapsed" x-transition:enter="duration-300 ease-out" x-transition:enter-start="opacity-0 -translate-x-2" x-transition:enter-end="opacity-100 translate-x-0">
                        Payroll
                    </div>
                </div>
            </a>
            </div>

            {{-- Nav Section --}}
            <div class="flex-1 overflow-y-auto overflow-x-hidden py-4 custom-scrollbar">
                {{-- Group: Main --}}
                <div class="px-3 mb-6">
                    <p class="px-4 mb-3 text-[10px] font-bold uppercase tracking-[0.1em] text-slate-500 overflow-hidden" x-show="!sidebarCollapsed">
                        General
                    </p>
                    <nav class="space-y-1">
                        <a href="{{ route('dashboard') }}" class="sidebar-item flex items-center gap-3 px-4 py-2.5 rounded-r-lg group {{ request()->routeIs('dashboard') ? 'sidebar-item-active' : '' }}">
                            <svg class="w-5 h-5 flex-shrink-0 group-hover:scale-110 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                <rect width="7" height="9" x="3" y="3" rx="1" />
                                <rect width="7" height="5" x="14" y="3" rx="1" />
                                <rect width="7" height="9" x="14" y="12" rx="1" />
                                <rect width="7" height="5" x="3" y="16" rx="1" />
                            </svg>
                            <span class="font-medium overflow-hidden whitespace-nowrap" x-show="!sidebarCollapsed">Analytics Overview</span>
                        </a>
                    </nav>
                </div>

                {{-- Group: Operations --}}
                <div class="px-3 mb-6">
                    <p class="px-4 mb-3 text-[10px] font-bold uppercase tracking-[0.1em] text-slate-500 overflow-hidden" x-show="!sidebarCollapsed">
                        Operations
                    </p>
                    <nav class="space-y-1">
                        @if (auth()->check() && in_array(auth()->user()->role, ['admin']))
                            <a href="{{ route('employees.index') }}" class="sidebar-item flex items-center gap-3 px-4 py-2.5 rounded-r-lg group {{ request()->routeIs('employees.*') ? 'sidebar-item-active' : '' }}">
                                <svg class="w-5 h-5 flex-shrink-0 group-hover:scale-110 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                    <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" /><circle cx="9" cy="7" r="4" /><path d="M22 21v-2a4 4 0 0 0-3-3.87" /><path d="M16 3.13a4 4 0 0 1 0 7.75" />
                                </svg>
                                <span class="font-medium overflow-hidden whitespace-nowrap" x-show="!sidebarCollapsed">Staff Management</span>
                            </a>
                        @endif

                        <a href="{{ route('attendance.index') }}" class="sidebar-item flex items-center gap-3 px-4 py-2.5 rounded-r-lg group {{ request()->routeIs('attendance.*') ? 'sidebar-item-active' : '' }}">
                            <svg class="w-5 h-5 flex-shrink-0 group-hover:scale-110 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                <rect width="18" height="18" x="3" y="4" rx="2" ry="2" />
                                <line x1="16" x2="16" y1="2" y2="6" />
                                <line x1="8" x2="8" y1="2" y2="6" />
                                <line x1="3" x2="21" y1="10" y2="10" />
                                <path d="m9 16 2 2 4-4" />
                            </svg>
                            <span class="font-medium overflow-hidden whitespace-nowrap" x-show="!sidebarCollapsed">Attendance Tracking</span>
                        </a>
                    </nav>
                </div>

                {{-- Group: Administration --}}
                @if (auth()->check() && in_array(auth()->user()->role, ['admin']))
                <div class="px-3">
                    <p class="px-4 mb-3 text-[10px] font-bold uppercase tracking-[0.1em] text-slate-500 overflow-hidden" x-show="!sidebarCollapsed">
                        Administration
                    </p>
                    <nav x-data="{ payrollOpen: {{ (request()->routeIs('payroll.*') || request()->routeIs('payroll.monthly.*')) ? 'true' : 'false' }} }" class="space-y-1">
                        <button @click="payrollOpen = !payrollOpen" class="w-full sidebar-item flex items-center gap-3 px-4 py-2.5 rounded-r-lg group {{ (request()->routeIs('payroll.*') || request()->routeIs('payroll.monthly.*')) ? 'sidebar-item-active' : '' }}">
                            <svg class="w-5 h-5 flex-shrink-0 group-hover:scale-110 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                <rect width="20" height="14" x="2" y="5" rx="2" />
                                <line x1="2" x2="22" y1="10" y2="10" />
                            </svg>
                            <span class="font-medium overflow-hidden whitespace-nowrap" x-show="!sidebarCollapsed">Payroll Hub</span>
                            <svg x-show="!sidebarCollapsed" class="w-3 h-3 ml-auto transition-transform duration-200" :class="payrollOpen ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                        
                        <div x-show="payrollOpen && !sidebarCollapsed" x-collapse>
                            <div class="mt-1 space-y-1 pl-9 pr-2">
                                <a href="{{ route('payroll.index') }}" class="flex items-center gap-2 px-3 py-1.5 rounded-md text-[13px] hover:text-white transition-colors {{ request()->routeIs('payroll.index') ? 'text-indigo-400 font-medium' : 'text-slate-500' }}">
                                    <div class="w-1.5 h-1.5 rounded-full {{ request()->routeIs('payroll.index') ? 'bg-indigo-400' : 'bg-slate-700' }}"></div>
                                    Weekly Processing
                                </a>
                                <a href="{{ route('payroll.monthly.index') }}" class="flex items-center gap-2 px-3 py-1.5 rounded-md text-[13px] hover:text-white transition-colors {{ request()->is('payroll/monthly*') ? 'text-indigo-400 font-medium' : 'text-slate-500' }}">
                                    <div class="w-1.5 h-1.5 rounded-full {{ request()->is('payroll/monthly*') ? 'bg-indigo-400' : 'bg-slate-700' }}"></div>
                                    Monthly Reports
                                </a>
                            </div>
                        </div>
                    </nav>
                </div>
                @endif
            </div>

            {{-- Sidebar Footer --}}
            <div class="px-3 py-4 border-t border-white/5 bg-black/10">
                <div class="flex items-center gap-3 px-3 py-2">
                    <div class="w-8 h-8 rounded-full bg-indigo-500/10 border border-indigo-500/20 flex items-center justify-center text-indigo-400 font-bold text-xs ring-4 ring-indigo-500/5 flex-shrink-0">
                        {{ strtoupper(mb_substr(auth()->user()->name ?? 'A', 0, 1)) }}
                    </div>
                    <div class="overflow-hidden" x-show="!sidebarCollapsed">
                        <p class="text-xs font-semibold text-white truncate">{{ auth()->user()->name ?? 'Administrator' }}</p>
                        <p class="text-[10px] text-slate-500 uppercase tracking-wider font-bold">Admin Level</p>
                    </div>
                </div>
            </div>
        </aside>

        {{-- Mobile Sidebar Overlay --}}
        <div x-show="mobileSidebarOpen" 
             x-cloak
             class="fixed inset-0 z-[60] lg:hidden" 
             role="dialog" aria-modal="true">
            {{-- Backdrop --}}
            <div x-show="mobileSidebarOpen" 
                 x-transition:enter="transition-opacity ease-linear duration-300"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 x-transition:leave="transition-opacity ease-linear duration-300"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0"
                 @click="mobileSidebarOpen = false"
                 class="fixed inset-0 bg-slate-900/80 backdrop-blur-sm" aria-hidden="true"></div>

            {{-- Sidebar Content --}}
            <div x-show="mobileSidebarOpen"
                 x-transition:enter="transition ease-in-out duration-300 transform"
                 x-transition:enter-start="-translate-x-full"
                 x-transition:enter-end="translate-x-0"
                 x-transition:leave="transition ease-in-out duration-300 transform"
                 x-transition:leave-start="translate-x-0"
                 x-transition:leave-end="-translate-x-full"
                 class="relative flex w-full max-w-xs flex-1 flex-col bg-[#0f172a] shadow-xl h-full">
                
                <div class="absolute right-0 top-0 -mr-12 pt-4">
                    <button type="button" @click="mobileSidebarOpen = false" class="ml-1 flex h-10 w-10 items-center justify-center rounded-full focus:outline-none focus:ring-2 focus:ring-inset focus:ring-white">
                        <span class="sr-only">Close sidebar</span>
                        <svg class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="h-16 flex items-center px-6 border-b border-white/5">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-indigo-500 to-indigo-700 flex items-center justify-center text-white shadow-lg shadow-indigo-900/50 flex-shrink-0">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5" />
                            </svg>
                        </div>
                        <div class="font-black text-white tracking-tighter text-xl">
                            Payroll<span class="text-indigo-400">Pro</span>
                        </div>
                    </div>
                </div>

                <div class="flex-1 overflow-y-auto pt-6 pb-4 px-3">
                    <nav class="space-y-6">
                        <div>
                            <p class="px-4 mb-3 text-[10px] font-bold uppercase tracking-[0.1em] text-slate-500">Main Menu</p>
                            <a href="{{ route('dashboard') }}" class="sidebar-item flex items-center gap-3 px-4 py-2.5 rounded-lg text-slate-400 {{ request()->routeIs('dashboard') ? 'sidebar-item-active' : '' }}">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
                                </svg>
                                <span class="font-medium">Dashboard</span>
                            </a>
                        </div>
                        
                        <div>
                            <p class="px-4 mb-3 text-[10px] font-bold uppercase tracking-[0.1em] text-slate-500">Operations</p>
                            <div class="space-y-1">
                                @if (auth()->check() && in_array(auth()->user()->role, ['admin']))
                                    <a href="{{ route('employees.index') }}" class="sidebar-item flex items-center gap-3 px-4 py-2.5 rounded-lg text-slate-400 {{ request()->routeIs('employees.*') ? 'sidebar-item-active' : '' }}">
                                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 20v-2a4 4 0 00-4-4H7a4 4 0 00-4 4v2m4-10a4 4 0 11-8 0 4 4 0 018 0z" />
                                        </svg>
                                        <span class="font-medium">Staff Directory</span>
                                    </a>
                                @endif
                                <a href="{{ route('attendance.index') }}" class="sidebar-item flex items-center gap-3 px-4 py-2.5 rounded-lg text-slate-400 {{ request()->routeIs('attendance.*') ? 'sidebar-item-active' : '' }}">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                    <span class="font-medium">Daily Attendance</span>
                                </a>
                            </div>
                        </div>

                        @if (auth()->check() && in_array(auth()->user()->role, ['admin']))
                        <div>
                            <p class="px-4 mb-3 text-[10px] font-bold uppercase tracking-[0.1em] text-slate-500">Administration</p>
                            <div class="space-y-1">
                                <div x-data="{ payrollOpen: {{ (request()->routeIs('payroll.*') || request()->is('payroll/monthly*')) ? 'true' : 'false' }} }">
                                    <button @click="payrollOpen = !payrollOpen" class="sidebar-item w-full flex items-center gap-3 px-4 py-2.5 rounded-lg text-slate-400 {{ (request()->routeIs('payroll.*') || request()->is('payroll/monthly*')) ? 'sidebar-item-active' : '' }}">
                                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                            <rect width="20" height="14" x="2" y="5" rx="2" /><line x1="2" x2="22" y1="10" y2="10" />
                                        </svg>
                                        <span class="font-medium mr-auto">Payroll Hub</span>
                                        <svg class="w-3 h-3 transition-transform duration-200" :class="payrollOpen ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                                        </svg>
                                    </button>
                                    <div x-show="payrollOpen" x-collapse class="mt-1 space-y-1">
                                        <a href="{{ route('payroll.index') }}" class="flex items-center gap-3 px-6 py-2 rounded-lg text-sm {{ request()->routeIs('payroll.index') ? 'text-indigo-400 font-medium' : 'text-slate-500' }}">
                                            <div class="w-1 h-1 rounded-full {{ request()->routeIs('payroll.index') ? 'bg-indigo-400' : 'bg-slate-700' }}"></div>
                                            Weekly Processing
                                        </a>
                                        <a href="{{ route('payroll.monthly.index') }}" class="flex items-center gap-3 px-6 py-2 rounded-lg text-sm {{ request()->is('payroll/monthly*') ? 'text-indigo-400 font-medium' : 'text-slate-500' }}">
                                            <div class="w-1 h-1 rounded-full {{ request()->is('payroll/monthly*') ? 'bg-indigo-400' : 'bg-slate-700' }}"></div>
                                            Monthly Reports
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endif
                    </nav>
                </div>

                <div class="px-6 py-4 border-t border-white/5">
                    <div class="flex items-center gap-3 py-2">
                        <div class="w-10 h-10 rounded-full bg-indigo-500/10 flex items-center justify-center text-indigo-400 font-bold">
                            {{ strtoupper(mb_substr(auth()->user()->name ?? 'A', 0, 1)) }}
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-white">{{ auth()->user()->name ?? 'Administrator' }}</p>
                            <p class="text-xs text-slate-500 uppercase tracking-wider font-bold">Admin Level</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Main content --}}
        <div class="flex-1 flex flex-col min-w-0">

            {{-- Top bar --}}
            <header class="h-16 bg-white/80 backdrop-blur-md border-b border-slate-200 flex items-center justify-between px-6 sticky top-0 z-40">
                <div class="flex items-center gap-4">
                    <button type="button" @click="sidebarCollapsed = !sidebarCollapsed" class="hidden lg:flex p-2 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-all">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h8m-8 6h16" />
                        </svg>
                    </button>
                    
                    <button type="button" @click="mobileSidebarOpen = true" class="lg:hidden p-2 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-all">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>

                    <h1 class="text-base font-bold text-slate-800 tracking-tight">
                        @yield('page_title', 'Overview')
                    </h1>
                </div>

                <div class="flex items-center gap-3">
                    <div class="flex items-center gap-2 px-3 py-1.5 rounded-full border border-slate-200 hover:border-slate-300 transition-colors cursor-pointer group shadow-sm bg-white">
                        <div class="w-6 h-6 rounded-full bg-slate-100 flex items-center justify-center text-[10px] font-bold text-slate-600">
                            {{ strtoupper(mb_substr(auth()->user()->name ?? 'A', 0, 1)) }}
                        </div>
                        <span class="text-xs font-semibold text-slate-600 hidden sm:block">{{ auth()->user()->name ?? 'Admin' }}</span>
                        
                        <form action="{{ route('logout') }}" method="POST" class="flex items-center ml-2 pl-2 border-l border-slate-200 m-0 p-0">
                            @csrf
                            <button type="submit" class="p-1 rounded-md text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition-all flex items-center justify-center outline-none ring-0 border-0" title="Logout">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                                </svg>
                            </button>
                        </form>
                    </div>
                </div>
            </header>

            {{-- Page body --}}
            <main class="flex-1 p-6 relative">
                {{-- Global Alerts --}}
                @if (session('success'))
                    <div class="max-w-4xl mx-auto mb-6 flex items-center justify-between gap-3 p-4 rounded-xl bg-emerald-50 border border-emerald-100 text-emerald-800 shadow-sm animate-in fade-in slide-in-from-top-4 duration-300">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-lg bg-emerald-100 flex items-center justify-center flex-shrink-0">
                                <svg class="w-5 h-5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                            </div>
                            <span class="text-sm font-medium">{{ session('success') }}</span>
                        </div>
                        <button class="text-emerald-400 hover:text-emerald-600 p-1" @click="$el.parentElement.remove()">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                        </button>
                    </div>
                @endif

                @if (session('error'))
                    <div
                        class="mb-4 rounded-lg bg-rose-50 border border-rose-200 text-rose-800 px-4 py-3 text-sm">
                        {{ session('error') }}
                    </div>
                @endif

                @yield('content')
            </main>
        </div>

    </div>

</body>

</html>
