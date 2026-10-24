@extends('layouts.public')

@section('title', 'Beranda')

@section('content')
<main class="mx-auto max-w-3xl px-4 py-16 sm:px-6 sm:py-24 lg:px-8">
    <h1 class="text-3xl font-semibold tracking-tight sm:text-4xl">SoESIS WebGIS</h1>
    <p class="mt-4 text-base leading-relaxed text-slate-600">
        Platform data spasial untuk menelusuri dan melihat dataset yang telah ditinjau dan disetujui.
        Setiap dataset dilengkapi metadata, sumber, dan lisensi, serta dapat dibuka langsung di peta.
    </p>
    <div class="mt-8 flex flex-wrap gap-3">
        <a href="{{ route('catalogue.index') }}" class="rounded-md bg-emerald-700 px-5 py-2.5 text-sm font-medium text-white hover:bg-emerald-800">Buka katalog</a>
        <a href="{{ route('catalogue.map') }}" class="rounded-md bg-white px-5 py-2.5 text-sm font-medium text-slate-800 ring-1 ring-slate-300 ring-inset hover:bg-slate-50">Lihat peta</a>
    </div>
</main>
@endsection
