<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>@yield('title', 'Sign in')</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    {{-- Compiled CSS + JS via Vite (Tailwind v4 + Alpine.js) --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
    </style>
</head>

<body class="min-h-screen bg-slate-100 text-slate-700 relative overflow-hidden">

    <div class="min-h-screen flex items-center justify-center px-4 py-8 relative z-10">
        <div
            class="w-full max-w-6xl grid grid-cols-1 lg:grid-cols-2 gap-0 rounded-3xl shadow-2xl overflow-hidden border border-white/10">

            {{-- left section — solid brand color --}}
            <div
                class="flex flex-col justify-center px-8 sm:px-10 py-10"
                style="background-color: #9e2a2b;">

                {{-- brand row --}}
                <div class="flex items-center gap-3 mb-8">
                    <div
                        class="w-9 h-9 rounded-2xl flex items-center justify-center text-xs font-semibold"
                        style="background:rgba(255,255,255,0.15);">
                        <img style="border-radius:6px;" src="/fav.png">
                    </div>
                    <div>
                        <div class="text-xs font-semibold tracking-wide text-white/90 uppercase">
                            Payroll App
                        </div>
                        <div class="text-[11px] text-white/50">
                            Weekly payroll made simple
                        </div>
                    </div>
                </div>

                {{-- hero text --}}
                <h1 class="text-3xl sm:text-4xl font-semibold text-white leading-tight">
                    Welcome back
                    <span class="block text-white/60 text-lg sm:text-xl mt-2">
                        Run payroll in minutes, not hours
                    </span>
                </h1>

                {{-- small highlight chips --}}
                <div class="mt-6 flex flex-wrap gap-2 text-[11px] text-white/80">
                    <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full" style="background:rgba(255,255,255,0.12);">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-300"></span>
                        Live attendance overview
                    </span>
                    <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full" style="background:rgba(255,255,255,0.12);">
                        <span class="w-1.5 h-1.5 rounded-full bg-sky-300"></span>
                        Cash and bank split
                    </span>
                    <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full" style="background:rgba(255,255,255,0.12);">
                        <span class="w-1.5 h-1.5 rounded-full bg-amber-300"></span>
                        Weekly export ready
                    </span>
                </div>

                {{-- tiny stats row --}}
                <div class="mt-8 grid grid-cols-3 gap-4 text-xs text-white/60">
                    <div>
                        <div class="text-white font-semibold text-base">6</div>
                        <div class="mt-1">days tracked per week</div>
                    </div>
                    <div>
                        <div class="text-white font-semibold text-base">2</div>
                        <div class="mt-1">employee types</div>
                    </div>
                    <div>
                        <div class="text-white font-semibold text-base">1</div>
                        <div class="mt-1">simple weekly sheet</div>
                    </div>
                </div>

            </div>

            {{-- right login card --}}
            <div class="flex items-center justify-center py-10 px-6 sm:px-10">
                <div
                    class="w-full max-w-md bg-white/90 backdrop-blur-xl rounded-2xl border border-slate-200 shadow-lg transition-all duration-300 hover:shadow-2xl hover:-translate-y-1">

                    <div class="px-7 sm:px-8 py-8 sm:py-9">
                        {{-- optional top badge, can remove if your @section already includes heading --}}
                        <div class="mb-5 flex items-center justify-between text-xs text-slate-500">
                            <span class="inline-flex items-center gap-2">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                Secure admin access
                            </span>
                        </div>

                        @yield('content')
                    </div>

                </div>
            </div>
        </div>
    </div>

</body>

</html>
