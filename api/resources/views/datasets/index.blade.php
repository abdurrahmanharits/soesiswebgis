@extends('layouts.app')

@section('title', 'Datasets')

@section('content')
<main data-page="datasets.index" class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">Spatial datasets</h1>
            <p class="mt-1 text-sm text-slate-600">Uploaded Shapefiles and GeoTIFFs, their processing status and core metadata.</p>
        </div>
        <button type="button" data-open-upload
                class="inline-flex items-center justify-center gap-2 rounded-md bg-emerald-700 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-emerald-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700">
            <svg class="size-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10 3a1 1 0 0 1 .7.3l4 4a1 1 0 0 1-1.4 1.4L11 6.4V13a1 1 0 1 1-2 0V6.4L6.7 8.7a1 1 0 0 1-1.4-1.4l4-4A1 1 0 0 1 10 3Zm-6 12a1 1 0 0 1 1 1h10a1 1 0 1 1 2 0 2 2 0 0 1-2 2H5a2 2 0 0 1-2-2 1 1 0 0 1 1-1Z"/></svg>
            Upload Dataset
        </button>
    </div>

    {{-- Summary --}}
    <dl class="mt-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
        @foreach ([
            ['summary-total', 'Total datasets', 'bg-slate-400'],
            ['summary-ready', 'Ready', 'bg-emerald-500'],
            ['summary-processing', 'Processing', 'bg-sky-500'],
            ['summary-failed', 'Failed', 'bg-rose-500'],
        ] as [$id, $label, $dot])
            <div class="rounded-lg border border-slate-200 bg-white px-5 py-4">
                <dt class="flex items-center gap-2 text-sm text-slate-600">
                    <span class="size-2 rounded-full {{ $dot }}" aria-hidden="true"></span>{{ $label }}
                </dt>
                <dd id="{{ $id }}" class="mt-2 text-3xl font-semibold tabular-nums">–</dd>
            </div>
        @endforeach
    </dl>

    <section class="mt-8 rounded-lg border border-slate-200 bg-white" aria-labelledby="datasets-heading">
        <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3 sm:px-5">
            <h2 id="datasets-heading" class="text-base font-semibold">Datasets</h2>
            <span id="dataset-count" class="text-sm text-slate-500" aria-live="polite"></span>
        </div>

        {{-- Loading --}}
        <div id="dataset-loading" class="divide-y divide-slate-100" aria-busy="true" aria-label="Loading datasets">
            @for ($i = 0; $i < 4; $i++)
                <div class="flex animate-pulse items-center gap-4 px-5 py-4">
                    <div class="h-4 w-1/3 rounded bg-slate-200"></div>
                    <div class="h-4 w-16 rounded bg-slate-100"></div>
                    <div class="h-4 w-20 rounded bg-slate-100"></div>
                    <div class="ml-auto h-4 w-14 rounded bg-slate-100"></div>
                </div>
            @endfor
        </div>

        {{-- Error --}}
        <div id="dataset-error" class="px-5 py-12 text-center" role="alert" hidden>
            <p class="text-sm font-semibold text-rose-800">Could not load datasets</p>
            <p data-message class="mt-1 text-sm text-slate-600"></p>
            <button id="dataset-retry" type="button" class="mt-4 rounded-md px-3 py-1.5 text-sm font-medium text-slate-700 ring-1 ring-slate-300 ring-inset hover:bg-slate-50">Try again</button>
        </div>

        {{-- Empty --}}
        <div id="dataset-empty" class="px-5 py-16 text-center" hidden>
            <div class="mx-auto grid size-12 place-items-center rounded-full bg-slate-100 text-slate-400" aria-hidden="true">
                <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M9 6.75 3.75 4.5v12.75L9 19.5m0-12.75 6-2.25m-6 2.25v12.75m6-15 5.25 2.25v12.75L15 17.25m0-12.75v12.75m0 0-6 2.25"/></svg>
            </div>
            <p class="mt-4 text-sm font-semibold">No spatial datasets yet.</p>
            <p class="mx-auto mt-1 max-w-md text-sm text-slate-600">Upload a Shapefile or GeoTIFF to start building the SoESIS spatial catalogue.</p>
            <button type="button" data-open-upload class="mt-5 rounded-md bg-emerald-700 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-800">Upload Dataset</button>
        </div>

        {{-- Filters + table --}}
        <div id="dataset-content" hidden>
            <div class="flex flex-col gap-3 border-b border-slate-200 px-4 py-3 sm:flex-row sm:items-center sm:px-5">
                <label class="relative flex-1">
                    <span class="sr-only">Search datasets</span>
                    <svg class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 1 0 3.4 9.8l3.15 3.15a.75.75 0 1 0 1.06-1.06l-3.15-3.15A5.5 5.5 0 0 0 9 3.5ZM5 9a4 4 0 1 1 8 0 4 4 0 0 1-8 0Z" clip-rule="evenodd"/></svg>
                    <input id="dataset-search" type="search" placeholder="Search by title or filename…" autocomplete="off"
                           class="w-full rounded-md border-0 py-2 pr-3 pl-9 text-sm ring-1 ring-slate-300 ring-inset placeholder:text-slate-400 focus:ring-2 focus:ring-emerald-600">
                </label>
                <div class="grid grid-cols-2 gap-3 sm:flex">
                    <label>
                        <span class="sr-only">Filter by format</span>
                        <select id="filter-format" class="w-full rounded-md border-0 py-2 pr-8 pl-3 text-sm ring-1 ring-slate-300 ring-inset focus:ring-2 focus:ring-emerald-600">
                            <option value="">All formats</option>
                            <option value="shapefile">Shapefile</option>
                            <option value="geotiff">GeoTIFF</option>
                        </select>
                    </label>
                    <label>
                        <span class="sr-only">Filter by status</span>
                        <select id="filter-status" class="w-full rounded-md border-0 py-2 pr-8 pl-3 text-sm ring-1 ring-slate-300 ring-inset focus:ring-2 focus:ring-emerald-600">
                            <option value="">All statuses</option>
                            <option value="queued">Queued</option>
                            <option value="processing">Processing</option>
                            <option value="ready_for_curation">Ready</option>
                            <option value="failed">Failed</option>
                        </select>
                    </label>
                </div>
                <button id="filters-clear" type="button" class="text-sm font-medium text-slate-600 hover:text-slate-900" hidden>Clear</button>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[56rem] text-left text-sm">
                    <thead class="bg-slate-50 text-xs font-medium tracking-wide text-slate-500 uppercase">
                        <tr>
                            <th scope="col" class="px-4 py-3 font-medium">Dataset</th>
                            <th scope="col" class="px-4 py-3 font-medium">Format</th>
                            <th scope="col" class="px-4 py-3 font-medium">CRS</th>
                            <th scope="col" class="px-4 py-3 font-medium">Detail</th>
                            <th scope="col" class="px-4 py-3 font-medium">Status</th>
                            <th scope="col" class="px-4 py-3 font-medium">Created</th>
                            <th scope="col" class="px-4 py-3 text-right font-medium"><span class="sr-only">Action</span></th>
                        </tr>
                    </thead>
                    <tbody id="dataset-tbody" class="divide-y divide-slate-100"></tbody>
                </table>
            </div>

            <p id="dataset-no-results" class="px-5 py-10 text-center text-sm text-slate-600" hidden>No datasets match the current search or filters.</p>
        </div>
    </section>
</main>

@include('datasets.partials.upload-dialog')
@endsection
