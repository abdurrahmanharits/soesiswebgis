@extends('layouts.public')

@section('title', 'Dataset tidak tersedia')

@section('content')
{{-- 404 for unknown and unpublished (pending, rejected, failed, processing) datasets alike. --}}
<main class="mx-auto max-w-3xl px-4 py-16 text-center sm:px-6 lg:px-8">
    <p class="text-sm font-semibold text-emerald-700">404</p>
    <h1 class="mt-2 text-2xl font-semibold tracking-tight">Dataset tidak tersedia</h1>
    <p class="mt-2 text-sm text-slate-600">Dataset ini tidak ditemukan atau tidak lagi dipublikasikan di katalog.</p>
    <a href="{{ route('catalogue.index') }}" class="mt-6 inline-block rounded-md bg-emerald-700 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-800">Kembali ke katalog</a>
</main>
@endsection
