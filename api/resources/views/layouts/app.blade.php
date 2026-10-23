<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>@yield('title', 'Datasets') · SoESIS WebGIS</title>

        @fonts

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-slate-100 font-sans text-slate-900 antialiased">
        <header class="border-b border-slate-200 bg-white">
            <div class="mx-auto flex h-16 max-w-7xl items-center gap-8 px-4 sm:px-6 lg:px-8">
                <a href="{{ route('datasets.index') }}" class="flex items-center gap-3">
                    <span class="grid size-9 place-items-center rounded-md bg-emerald-700 text-sm font-semibold text-white" aria-hidden="true">S</span>
                    <span class="leading-tight">
                        <span class="block text-sm font-semibold tracking-tight">SoESIS WebGIS</span>
                        <span class="block text-xs text-slate-500">Spatial Data Platform</span>
                    </span>
                </a>

                <nav class="flex items-center gap-1 text-sm" aria-label="Main">
                    <a href="{{ route('datasets.index') }}"
                       @class([
                           'rounded-md px-3 py-2 font-medium',
                           'bg-slate-100 text-slate-900' => request()->routeIs('datasets.*'),
                           'text-slate-600 hover:text-slate-900' => ! request()->routeIs('datasets.*'),
                       ])
                       @if (request()->routeIs('datasets.*')) aria-current="page" @endif>
                        Datasets
                    </a>
                    <span class="cursor-not-allowed rounded-md px-3 py-2 text-slate-400" title="Map preview is planned for the next milestone" aria-disabled="true">
                        Map <span class="ml-1 rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-medium tracking-wide text-slate-500 uppercase">Soon</span>
                    </span>
                </nav>
            </div>
        </header>

        @yield('content')

        <div id="toast-region" class="pointer-events-none fixed inset-x-4 bottom-4 z-50 flex flex-col items-end gap-2 sm:inset-x-auto sm:right-6" aria-live="polite"></div>
    </body>
</html>
