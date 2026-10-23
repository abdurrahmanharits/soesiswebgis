@extends('layouts.app')

@section('title', 'Dataset')

@section('content')
<main data-page="datasets.show" data-dataset-id="{{ $datasetId }}" class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
    <a href="{{ route('datasets.index') }}" class="inline-flex items-center gap-1 text-sm text-slate-600 hover:text-slate-900">
        <span aria-hidden="true">←</span> All datasets
    </a>

    <div id="detail-loading" class="mt-6 animate-pulse space-y-4" aria-busy="true" aria-label="Loading dataset">
        <div class="h-7 w-1/3 rounded bg-slate-200"></div>
        <div class="h-4 w-1/4 rounded bg-slate-200"></div>
        <div class="h-64 rounded-lg bg-white"></div>
    </div>

    <div id="detail-error" class="mt-6 rounded-lg border border-slate-200 bg-white px-5 py-12 text-center" role="alert" hidden>
        <p class="text-sm font-semibold text-rose-800">Could not load dataset</p>
        <p data-message class="mt-1 text-sm text-slate-600"></p>
        <button id="detail-retry" type="button" class="mt-4 rounded-md px-3 py-1.5 text-sm font-medium text-slate-700 ring-1 ring-slate-300 ring-inset hover:bg-slate-50">Try again</button>
    </div>

    <div id="detail-content" class="mt-4" hidden>
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0">
                <h1 id="detail-title" class="text-2xl font-semibold tracking-tight break-words"></h1>
                <p id="detail-subtitle" class="mt-1 truncate font-mono text-sm text-slate-500"></p>
            </div>
            <div class="flex shrink-0 items-center gap-3">
                <div id="detail-badges" class="flex items-center gap-2"></div>
                {{-- Shown by detail.js for approved datasets only. --}}
                <a id="detail-open-map" href="{{ route('map.index') }}" class="rounded-md bg-white px-3 py-1.5 text-sm font-medium text-emerald-700 ring-1 ring-emerald-200 ring-inset hover:bg-emerald-50" hidden>Open in map</a>
            </div>
        </div>

        <div id="detail-notice" class="mt-6 empty:hidden"></div>

        @include('datasets.partials.preview')

        @include('datasets.partials.curation')

        <div id="detail-body" class="mt-6 space-y-6"></div>
    </div>
</main>
@endsection
