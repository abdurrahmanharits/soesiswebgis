<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        {{-- Read by resources/js/datasets/api.js for write requests and permission-aware UI. --}}
        <meta name="csrf-token" content="{{ csrf_token() }}">
        @auth
            <meta name="soesis-user" content="{{ json_encode(auth()->user()->toClient()) }}">
        @endauth

        <title>@yield('title', 'Datasets') · SoESIS WebGIS</title>

        @fonts

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-slate-100 font-sans text-slate-900 antialiased">
        <header class="border-b border-slate-200 bg-white">
            <div class="mx-auto flex h-16 max-w-7xl items-center gap-8 px-4 sm:px-6 lg:px-8">
                <a href="{{ auth()->check() ? route('datasets.index') : route('home') }}" class="flex items-center gap-3">
                    <span class="grid size-9 place-items-center rounded-md bg-emerald-700 text-sm font-semibold text-white" aria-hidden="true">S</span>
                    <span class="leading-tight">
                        <span class="block text-sm font-semibold tracking-tight">SoESIS WebGIS</span>
                        <span class="block text-xs text-slate-500">Spatial Data Platform</span>
                    </span>
                </a>

                @auth
                    @php
                        $links = ['datasets.index' => ['Datasets', 'datasets.*'], 'map.index' => ['Map', 'map.*']];
                        if (auth()->user()->isAdmin()) {
                            $links['admin.users.index'] = ['Users', 'admin.*'];
                        }
                    @endphp

                    <nav class="flex min-w-0 items-center gap-1 overflow-x-auto text-sm" aria-label="Main">
                        @foreach ($links as $name => [$label, $pattern])
                            <a href="{{ route($name) }}"
                               @class([
                                   'rounded-md px-3 py-2 font-medium whitespace-nowrap',
                                   'bg-slate-100 text-slate-900' => request()->routeIs($pattern),
                                   'text-slate-600 hover:text-slate-900' => ! request()->routeIs($pattern),
                               ])
                               @if (request()->routeIs($pattern)) aria-current="page" @endif>
                                {{ $label }}
                            </a>
                        @endforeach
                        {{-- The public area (Indonesian, published datasets only). --}}
                        <a href="{{ route('catalogue.index') }}" class="rounded-md px-3 py-2 font-medium whitespace-nowrap text-slate-600 hover:text-slate-900">
                            Catalogue
                        </a>
                    </nav>

                    <div class="ml-auto flex shrink-0 items-center gap-3">
                        <div class="hidden text-right leading-tight sm:block">
                            <p class="text-sm font-medium text-slate-900">{{ auth()->user()->name }}</p>
                            <p class="text-xs text-slate-500">{{ auth()->user()->roleLabel() }}</p>
                        </div>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="rounded-md px-3 py-1.5 text-sm font-medium text-slate-700 ring-1 ring-slate-300 ring-inset hover:bg-slate-50">Log out</button>
                        </form>
                    </div>
                @endauth
            </div>
        </header>

        @yield('content')

        <div id="toast-region" class="pointer-events-none fixed inset-x-4 bottom-4 z-50 flex flex-col items-end gap-2 sm:inset-x-auto sm:right-6" aria-live="polite"></div>
    </body>
</html>
