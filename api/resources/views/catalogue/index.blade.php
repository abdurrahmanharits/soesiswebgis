@extends('layouts.public')

@section('title', 'Katalog Dataset')

@section('content')
@php($field = 'w-full rounded-md border-0 py-2 text-sm ring-1 ring-slate-300 ring-inset focus:ring-2 focus:ring-emerald-600')
{{-- Wired by resources/js/catalogue/list.js; data from GET /api/catalogue (published datasets only). --}}
<main data-page="catalogue.index" class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
    <div>
        <h1 class="text-2xl font-semibold tracking-tight">Katalog Dataset</h1>
        <p class="mt-1 text-sm text-slate-600">Dataset spasial yang telah ditinjau dan disetujui. Buka detail untuk melihat metadata, sumber, dan lisensi.</p>
    </div>

    <form id="catalogue-filters" class="mt-6 rounded-lg border border-slate-200 bg-white p-4" role="search" aria-label="Cari dan filter dataset">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-6">
            <label class="sm:col-span-2 lg:col-span-3">
                <span class="mb-1 block text-xs font-medium text-slate-600">Cari</span>
                <input name="q" type="search" autocomplete="off" placeholder="Judul, deskripsi, kata kunci, sumber…" class="{{ $field }} px-3 placeholder:text-slate-400">
            </label>
            <label>
                <span class="mb-1 block text-xs font-medium text-slate-600">Format</span>
                <select name="format" class="{{ $field }} pr-8 pl-3">
                    <option value="">Semua format</option>
                    <option value="shapefile">Vektor (Shapefile)</option>
                    <option value="geotiff">Raster (GeoTIFF)</option>
                </select>
            </label>
            <label>
                <span class="mb-1 block text-xs font-medium text-slate-600">Kategori topik</span>
                <select name="topic" class="{{ $field }} pr-8 pl-3"><option value="">Semua topik</option></select>
            </label>
            <label>
                <span class="mb-1 block text-xs font-medium text-slate-600">Lisensi</span>
                <select name="license" class="{{ $field }} pr-8 pl-3"><option value="">Semua lisensi</option></select>
            </label>
            <label>
                <span class="mb-1 block text-xs font-medium text-slate-600">Kata kunci</span>
                <select name="keyword" class="{{ $field }} pr-8 pl-3"><option value="">Semua kata kunci</option></select>
            </label>
            <label>
                <span class="mb-1 block text-xs font-medium text-slate-600">Periode dari</span>
                <input name="from" type="date" class="{{ $field }} px-3">
            </label>
            <label>
                <span class="mb-1 block text-xs font-medium text-slate-600">Periode sampai</span>
                <input name="to" type="date" class="{{ $field }} px-3">
            </label>
            <label>
                <span class="mb-1 block text-xs font-medium text-slate-600">Urutkan</span>
                <select name="sort" class="{{ $field }} pr-8 pl-3">
                    <option value="newest">Terbaru</option>
                    <option value="oldest">Terlama</option>
                    <option value="title_asc">Judul A–Z</option>
                    <option value="title_desc">Judul Z–A</option>
                </select>
            </label>
            <div class="flex items-end">
                <button type="button" data-clear-filters class="w-full rounded-md px-3 py-2 text-sm font-medium text-slate-700 ring-1 ring-slate-300 ring-inset hover:bg-slate-50 disabled:opacity-50" disabled>Hapus filter</button>
            </div>
        </div>
        <p class="mt-3 text-xs text-slate-500">Filter periode menampilkan dataset yang rentang waktunya beririsan dengan periode yang dipilih.</p>
    </form>

    <p id="catalogue-summary" class="mt-6 text-sm text-slate-600" aria-live="polite"></p>

    <p id="catalogue-loading" class="mt-6 flex items-center gap-2 text-sm text-slate-600">
        <span class="size-4 animate-spin rounded-full border-2 border-slate-300 border-t-emerald-700" aria-hidden="true"></span>
        Memuat katalog…
    </p>

    <div id="catalogue-error" class="mt-6 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800" role="alert" hidden>
        <p class="font-semibold">Katalog tidak dapat dimuat.</p>
        <p data-message class="mt-0.5"></p>
        <button type="button" data-retry class="mt-2 rounded-md bg-white px-3 py-1.5 text-xs font-medium text-slate-700 ring-1 ring-slate-300 ring-inset hover:bg-slate-50">Coba lagi</button>
    </div>

    <div id="catalogue-empty" class="mt-6 rounded-lg border border-dashed border-slate-300 bg-white px-6 py-12 text-center" hidden>
        <p class="font-medium text-slate-900">Belum ada dataset yang tersedia.</p>
        <p class="mt-1 text-sm text-slate-600">Dataset akan muncul di sini setelah ditinjau dan disetujui.</p>
    </div>

    <div id="catalogue-no-results" class="mt-6 rounded-lg border border-dashed border-slate-300 bg-white px-6 py-12 text-center" hidden>
        <p class="font-medium text-slate-900">Tidak ada dataset yang cocok dengan filter.</p>
        <button type="button" data-clear-filters class="mt-3 rounded-md px-3 py-1.5 text-sm font-medium text-emerald-700 ring-1 ring-emerald-200 ring-inset hover:bg-emerald-50">Hapus filter</button>
    </div>

    <ul id="catalogue-grid" class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3"></ul>

    <nav id="catalogue-pagination" class="mt-6 flex flex-wrap items-center justify-center gap-1" aria-label="Halaman" hidden></nav>

    <p class="mt-8 text-xs text-slate-500">
        Peta cakupan: © <a href="https://www.openstreetmap.org/copyright" class="underline hover:text-slate-700" target="_blank" rel="noopener">kontributor OpenStreetMap</a>.
    </p>

    <script type="application/json" id="catalogue-labels">@json(\App\Support\CatalogueLabels::forClient())</script>
</main>
@endsection
