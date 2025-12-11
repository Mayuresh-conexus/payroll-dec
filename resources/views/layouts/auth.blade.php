<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>@yield('title', 'Sign in')</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    {{-- Tailwind --}}
    <script src="https://cdn.tailwindcss.com"></script>

    <style>
        /* floating pastel blobs */
        .blob {
            position: absolute;
            border-radius: 9999px;
            filter: blur(60px);
            opacity: 0.55;
            animation: float 18s ease-in-out infinite;
        }

        .blob.blob-delay-1 {
            animation-delay: 4s;
        }

        .blob.blob-delay-2 {
            animation-delay: 8s;
        }

        @keyframes float {

            0%,
            100% {
                transform: translateY(0) translateX(0);
            }

            50% {
                transform: translateY(-40px) translateX(25px);
            }
        }

        /* subtle grid background */
        .grid-bg {
            background-image:
                linear-gradient(to right, rgba(148, 163, 184, 0.16) 1px, transparent 1px),
                linear-gradient(to bottom, rgba(148, 163, 184, 0.12) 1px, transparent 1px);
            background-size: 28px 28px;
        }
    </style>
</head>

<body class="min-h-screen bg-white text-slate-700 relative overflow-hidden">

    {{-- subtle grid layer --}}
    <div class="pointer-events-none absolute inset-0 grid-bg opacity-60"></div>

    {{-- soft floating background shapes --}}
    <div class="blob w-64 h-64 bg-blue-200 top-[-3rem] left-[-3rem]"></div>
    <div class="blob w-80 h-80 bg-rose-200 bottom-[-4rem] right-[10%] blob-delay-1"></div>
    <div class="blob w-72 h-72 bg-emerald-200 top-1/3 right-[-3rem] blob-delay-2"></div>

    <div class="min-h-screen flex items-center justify-center px-4 py-8 relative z-10">
        <div
            class="w-full max-w-6xl grid grid-cols-1 lg:grid-cols-2 gap-0 bg-white/70 backdrop-blur-2xl rounded-3xl border border-slate-200/70 shadow-xl">

            {{-- left section --}}
            <div
                class="flex flex-col justify-center px-8 sm:px-10 py-10 border-b lg:border-b-0 lg:border-r border-slate-200/70">

                {{-- brand row --}}
                <div class="flex items-center gap-3 mb-8">
                    <div
                        class="w-9 h-9 rounded-2xl bg-slate-900 text-white flex items-center justify-center text-xs font-semibold">
                        PA
                    </div>
                    <div>
                        <div class="text-xs font-semibold tracking-wide text-slate-900 uppercase">
                            Payroll App
                        </div>
                        <div class="text-[11px] text-slate-500">
                            Weekly payroll made simple
                        </div>
                    </div>
                </div>

                {{-- hero text --}}
                <h1 class="text-3xl sm:text-4xl font-semibold text-slate-900 leading-tight">
                    Welcome back
                    <span class="block text-slate-500 text-lg sm:text-xl mt-2">
                        Run payroll in minutes, not hours
                    </span>
                </h1>

                {{-- small highlight chips --}}
                <div class="mt-6 flex flex-wrap gap-2 text-[11px] text-slate-600">
                    <span
                        class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-slate-100 border border-slate-200">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                        Live attendance overview
                    </span>
                    <span
                        class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-slate-100 border border-slate-200">
                        <span class="w-1.5 h-1.5 rounded-full bg-sky-500"></span>
                        Cash and bank split
                    </span>
                    <span
                        class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-slate-100 border border-slate-200">
                        <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                        Weekly export ready
                    </span>
                </div>

                {{-- tiny stats row --}}
                <div class="mt-8 grid grid-cols-3 gap-4 text-xs text-slate-500">
                    <div>
                        <div class="text-slate-900 font-semibold text-base">6</div>
                        <div class="mt-1">days tracked per week</div>
                    </div>
                    <div>
                        <div class="text-slate-900 font-semibold text-base">2</div>
                        <div class="mt-1">employee types</div>
                    </div>
                    <div>
                        <div class="text-slate-900 font-semibold text-base">1</div>
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
