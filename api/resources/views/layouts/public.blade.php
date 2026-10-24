<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>@yield('title', 'Katalog') · SoESIS WebGIS</title>
        <meta name="description" content="@yield('description', 'Katalog dataset spasial SoESIS WebGIS yang telah dikurasi.')">

        @fonts

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    {{-- Public area: no upload or curation links. Internal pages use layouts/app. --}}
    <body class="min-h-screen bg-slate-100 font-sans text-slate-900 antialiased">
        <header class="border-b border-slate-200 bg-white">
            <div class="mx-auto flex h-16 max-w-7xl items-center gap-8 px-4 sm:px-6 lg:px-8">
                <a href="{{ route('catalogue.index') }}" class="flex items-center gap-3">
                    <span class="grid size-9 place-items-center rounded-md bg-emerald-700 text-sm font-semibold text-white" aria-hidden="true">S</span>
                    <span class="leading-tight">
                        <span class="block text-sm font-semibold tracking-tight">SoESIS WebGIS</span>
                        <span class="block text-xs text-slate-500">Katalog Data Spasial</span>
                    </span>
                </a>

                <nav class="flex items-center gap-1 text-sm" aria-label="Utama">
                    @foreach (['catalogue.index' => 'Katalog', 'catalogue.map' => 'Peta'] as $name => $label)
                        @php($active = $name === 'catalogue.map' ? request()->routeIs('catalogue.map') : request()->routeIs('catalogue.index', 'catalogue.show'))
                        <a href="{{ route($name) }}"
                           @class([
                               'rounded-md px-3 py-2 font-medium',
                               'bg-slate-100 text-slate-900' => $active,
                               'text-slate-600 hover:text-slate-900' => ! $active,
                           ])
                           @if ($active) aria-current="page" @endif>
                            {{ $label }}
                        </a>
                    @endforeach
                </nav>
            </div>
        </header>

        @yield('content')

        <div id="toast-region" class="pointer-events-none fixed inset-x-4 bottom-4 z-50 flex flex-col items-end gap-2 sm:inset-x-auto sm:right-6" aria-live="polite"></div>
    </body>
</html>
