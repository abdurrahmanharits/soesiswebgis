@extends('layouts.public')

@section('title', $dataset['title'])
@section('description', \Illuminate\Support\Str::limit($dataset['description'] ?? 'Dataset spasial di katalog SoESIS WebGIS.', 155))

@php
    $day = fn (?string $value) => $value ? \Illuminate\Support\Carbon::parse($value)->translatedFormat('j M Y') : null;
    $number = fn ($value) => $value === null ? null : number_format((float) $value, 0, ',', '.');

    $start = $dataset['temporal_start'];
    $end = $dataset['temporal_end'];
    $period = match (true) {
        ! $start && ! $end => null,
        ! $start || ! $end || $start === $end => $day($start ?: $end),
        default => $day($start).' – '.$day($end),
    };

    $extent = $dataset['extent'];
    $isRaster = $dataset['format'] === 'geotiff';

    $metadata = array_filter([
        'Kategori topik' => $topic,
        'Periode' => $period,
        'Cakupan geografis' => $dataset['geographic_description'],
        'Batas koordinat' => $extent
            ? sprintf('B %s · S %s · T %s · U %s', ...array_map(fn ($v) => number_format($v, 4, ',', '.'), $extent))
            : null,
        'Sistem koordinat' => $dataset['crs'],
        'Format' => \App\Support\CatalogueLabels::FORMATS[$dataset['format']] ?? $dataset['format'],
        'Tipe geometri' => $isRaster ? null : $dataset['geometry_type'],
        'Jumlah fitur' => $isRaster ? null : $number($dataset['feature_count']),
        'Jumlah band' => $isRaster ? $number($dataset['band_count']) : null,
        'Kontak' => $dataset['contact_name'],
        'Dipublikasikan' => $dataset['published_at'] ? \Illuminate\Support\Carbon::parse($dataset['published_at'])->translatedFormat('j F Y') : null,
    ], fn ($value) => filled($value));
@endphp

@section('content')
{{-- Metadata is server-rendered; resources/js/catalogue/detail.js adds the preview map. --}}
<main data-page="catalogue.show" data-dataset-id="{{ $dataset['id'] }}" data-format="{{ $dataset['format'] }}"
      class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
    <a href="{{ route('catalogue.index') }}" class="text-sm font-medium text-slate-600 hover:text-slate-900">← Kembali ke katalog</a>

    <div class="mt-4 flex flex-wrap items-start justify-between gap-4">
        <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-1.5">
                <span @class([
                    'inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium ring-1 ring-inset',
                    'bg-amber-50 text-amber-800 ring-amber-200' => $isRaster,
                    'bg-violet-50 text-violet-800 ring-violet-200' => ! $isRaster,
                ])>{{ \App\Support\CatalogueLabels::FORMATS[$dataset['format']] ?? $dataset['format'] }}</span>
                @if ($topic)
                    <span class="rounded-md bg-slate-200/70 px-2 py-0.5 text-xs text-slate-700">{{ $topic }}</span>
                @endif
            </div>
            <h1 class="mt-2 text-2xl font-semibold tracking-tight break-words">{{ $dataset['title'] }}</h1>
        </div>
        <a href="{{ route('catalogue.map', ['layers' => $dataset['id']]) }}"
           class="shrink-0 rounded-md bg-emerald-700 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-800">Buka di peta</a>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        <div class="min-w-0 space-y-6 lg:col-span-2">
            <section class="rounded-lg border border-slate-200 bg-white px-5 py-4" aria-labelledby="description-heading">
                <h2 id="description-heading" class="text-sm font-semibold text-slate-900">Deskripsi</h2>
                <p class="mt-2 text-sm leading-relaxed whitespace-pre-line text-slate-700">{{ $dataset['description'] ?: 'Tidak ada deskripsi.' }}</p>

                @if ($dataset['keywords'])
                    <ul class="mt-4 flex flex-wrap gap-1.5" aria-label="Kata kunci">
                        @foreach ($dataset['keywords'] as $keyword)
                            <li><a href="{{ route('catalogue.index', ['keyword' => $keyword]) }}" class="rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs text-emerald-800 hover:bg-emerald-100">{{ $keyword }}</a></li>
                        @endforeach
                    </ul>
                @endif
            </section>

            <div class="[&>section]:mt-0">
                @include('datasets.partials.preview')
            </div>

            @if ($dataset['lineage'])
                <section class="rounded-lg border border-slate-200 bg-white px-5 py-4" aria-labelledby="lineage-heading">
                    <h2 id="lineage-heading" class="text-sm font-semibold text-slate-900">Riwayat data (lineage)</h2>
                    <p class="mt-2 text-sm leading-relaxed whitespace-pre-line text-slate-700">{{ $dataset['lineage'] }}</p>
                </section>
            @endif
        </div>

        <aside class="min-w-0 space-y-6">
            <section class="rounded-lg border border-slate-200 bg-white px-5 py-4" aria-labelledby="source-heading">
                <h2 id="source-heading" class="text-sm font-semibold text-slate-900">Sumber dan lisensi</h2>
                <dl class="mt-3 space-y-3 text-sm">
                    @if ($dataset['source_organization'])
                        <div>
                            <dt class="text-xs text-slate-500">Organisasi sumber</dt>
                            <dd class="mt-0.5 text-slate-900">{{ $dataset['source_organization'] }}</dd>
                        </div>
                    @endif
                    @if ($dataset['source'])
                        <div>
                            <dt class="text-xs text-slate-500">Sumber</dt>
                            <dd class="mt-0.5 break-words whitespace-pre-line text-slate-900">{{ $dataset['source'] }}</dd>
                        </div>
                    @endif
                    <div>
                        <dt class="text-xs text-slate-500">Lisensi</dt>
                        <dd class="mt-0.5 font-medium text-slate-900">{{ $license ?? 'Tidak disebutkan' }}</dd>
                        @if ($dataset['license'] === 'restricted')
                            <dd class="mt-1 rounded-md bg-amber-50 px-2.5 py-1.5 text-xs text-amber-900 ring-1 ring-amber-200 ring-inset">
                                Penggunaan dataset ini dibatasi. Periksa batasan penggunaan dan hubungi sumber data sebelum memakainya.
                            </dd>
                        @endif
                    </div>
                    @if ($dataset['usage_constraints'])
                        <div>
                            <dt class="text-xs text-slate-500">Batasan penggunaan</dt>
                            <dd class="mt-0.5 whitespace-pre-line text-slate-900">{{ $dataset['usage_constraints'] }}</dd>
                        </div>
                    @endif
                </dl>

                <div class="mt-4 border-t border-slate-100 pt-3">
                    <p class="text-xs text-slate-500">Atribusi</p>
                    <p id="catalogue-attribution" class="mt-1 rounded-md bg-slate-50 px-3 py-2 text-sm text-slate-800 ring-1 ring-slate-200 ring-inset">{{ $attribution }}</p>
                    <button type="button" id="catalogue-copy-attribution" class="mt-2 text-xs font-medium text-emerald-700 hover:underline" hidden>Salin atribusi</button>
                </div>
            </section>

            <section class="rounded-lg border border-slate-200 bg-white px-5 py-4" aria-labelledby="metadata-heading">
                <h2 id="metadata-heading" class="text-sm font-semibold text-slate-900">Metadata</h2>
                <dl class="mt-3 space-y-3 text-sm">
                    @foreach ($metadata as $label => $value)
                        <div>
                            <dt class="text-xs text-slate-500">{{ $label }}</dt>
                            <dd class="mt-0.5 break-words text-slate-900">{{ $value }}</dd>
                        </div>
                    @endforeach
                </dl>
            </section>
        </aside>
    </div>
</main>
@endsection
