@php($public = $public ?? false)
@extends($public ? 'layouts.public' : 'layouts.app')

@section('title', __('Map'))

@section('content')
{{-- Full-height map below the 4rem header. Wired by resources/js/map/page.js.
     Also served as the public /catalogue/map (published datasets only, Indonesian). --}}
<main data-page="{{ $public ? 'catalogue.map' : 'map.index' }}" class="relative flex h-[calc(100dvh-4rem-1px)] overflow-hidden">
    <aside id="map-panel"
           class="absolute inset-y-0 left-0 z-20 flex w-80 max-w-[85vw] -translate-x-full flex-col border-r border-slate-200 bg-white shadow-xl transition-transform data-[open]:translate-x-0 lg:static lg:max-w-none lg:translate-x-0 lg:shadow-none"
           aria-label="{{ __('Layers') }}">
        <div class="flex items-center justify-between gap-2 border-b border-slate-200 px-4 py-3">
            <div>
                <h1 class="text-sm font-semibold">{{ __('Layers') }}</h1>
                <p id="map-layer-count" class="text-xs text-slate-500 tabular-nums">{{ __(':count of :max active', ['count' => 0, 'max' => 8]) }}</p>
            </div>
            <div class="flex items-center gap-1">
                <button id="map-fit" type="button" class="rounded-md px-2.5 py-1 text-xs font-medium text-slate-700 ring-1 ring-slate-300 ring-inset hover:bg-slate-50 disabled:opacity-50" disabled>{{ __('Fit active layers') }}</button>
                <button id="map-panel-close" type="button" class="rounded p-1 text-slate-400 hover:text-slate-600 lg:hidden" aria-label="{{ __('Close layers') }}">✕</button>
            </div>
        </div>

        <div class="min-h-0 flex-1 overflow-y-auto">
            <section class="border-b border-slate-200 px-4 py-3" aria-labelledby="map-active-heading">
                <h2 id="map-active-heading" class="text-xs font-semibold tracking-wide text-slate-500 uppercase">{{ __('Active') }}</h2>
                <ol id="map-active" class="mt-2 space-y-2"></ol>
                <p id="map-active-empty" class="mt-2 text-sm text-slate-500">{{ __('No layers added yet. Add datasets below.') }}</p>
            </section>

            <section class="px-4 py-3" aria-labelledby="map-catalogue-heading">
                <h2 id="map-catalogue-heading" class="text-xs font-semibold tracking-wide text-slate-500 uppercase">{{ __('Add datasets') }}</h2>

                <div class="mt-2 flex gap-2">
                    <label class="min-w-0 flex-1">
                        <span class="sr-only">{{ __('Search datasets') }}</span>
                        <input id="map-search" type="search" placeholder="{{ __('Search…') }}" autocomplete="off"
                               class="w-full rounded-md border-0 px-2.5 py-1.5 text-sm ring-1 ring-slate-300 ring-inset placeholder:text-slate-400 focus:ring-2 focus:ring-emerald-600">
                    </label>
                    <label>
                        <span class="sr-only">{{ __('Filter by format') }}</span>
                        <select id="map-format" class="rounded-md border-0 py-1.5 pr-7 pl-2 text-sm ring-1 ring-slate-300 ring-inset focus:ring-2 focus:ring-emerald-600">
                            <option value="">{{ __('All') }}</option>
                            <option value="shapefile">{{ __('Vector') }}</option>
                            <option value="geotiff">{{ __('Raster') }}</option>
                        </select>
                    </label>
                </div>

                <p id="map-catalogue-loading" class="mt-3 text-sm text-slate-500">{{ __('Loading datasets…') }}</p>
                <div id="map-catalogue-error" class="mt-3 text-sm" role="alert" hidden>
                    <p data-message class="text-rose-700"></p>
                    <button id="map-catalogue-retry" type="button" class="mt-2 rounded-md px-2.5 py-1 text-xs font-medium text-slate-700 ring-1 ring-slate-300 ring-inset hover:bg-slate-50">{{ __('Try again') }}</button>
                </div>
                <div id="map-catalogue-empty" class="mt-3 text-sm text-slate-600" hidden>
                    @if ($public)
                        <p>Belum ada dataset yang dipublikasikan.</p>
                        <a href="{{ route('catalogue.index') }}" class="mt-2 inline-block font-medium text-emerald-700 hover:underline">Ke katalog</a>
                    @else
                        <p>No datasets are available for the map yet. Datasets appear here once processed and approved or pending review.</p>
                        <a href="{{ route('datasets.index') }}" class="mt-2 inline-block font-medium text-emerald-700 hover:underline">Go to Datasets</a>
                    @endif
                </div>
                <p id="map-catalogue-no-results" class="mt-3 text-sm text-slate-500" hidden>{{ __('No datasets match.') }}</p>
                <ul id="map-catalogue" class="mt-2 divide-y divide-slate-100"></ul>
            </section>
        </div>
    </aside>

    <div class="relative min-w-0 flex-1">
        <div id="map-canvas" class="absolute inset-0 bg-slate-100"></div>

        <button id="map-panel-open" type="button"
                class="absolute top-3 left-12 z-10 rounded-md bg-white px-3 py-1.5 text-sm font-medium text-slate-800 shadow ring-1 ring-slate-200 lg:hidden">
            {{ __('Layers') }}
        </button>

        <div class="absolute top-3 right-3 z-10 inline-flex rounded-md bg-white shadow ring-1 ring-slate-200" role="group" aria-label="{{ __('Basemap') }}">
            <button type="button" data-basemap="osm" aria-pressed="true"
                    class="rounded-l-md px-3 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-50 aria-pressed:bg-slate-900 aria-pressed:text-white">{{ __('Map') }}</button>
            <button type="button" data-basemap="imagery" aria-pressed="false"
                    class="rounded-r-md px-3 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-50 aria-pressed:bg-slate-900 aria-pressed:text-white">{{ __('Imagery') }}</button>
        </div>

        <div id="map-popup" class="w-72 max-w-[80vw] rounded-lg bg-white text-sm shadow-lg ring-1 ring-slate-200" hidden>
            <div class="flex items-center justify-between gap-2 border-b border-slate-100 px-3 py-2">
                <p data-popup-title class="truncate font-semibold text-slate-900"></p>
                <button type="button" data-popup-close class="-mr-1 rounded p-1 text-slate-400 hover:text-slate-600" aria-label="{{ __('Close') }}">✕</button>
            </div>
            <div data-popup-body class="max-h-72 overflow-auto px-3 py-2"></div>
        </div>
    </div>
</main>
@endsection
