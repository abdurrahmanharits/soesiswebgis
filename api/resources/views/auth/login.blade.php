@extends('layouts.app')

@section('title', 'Sign in')

@section('content')
@php($input = 'mt-1 w-full rounded-md border-0 px-3 py-2 text-sm ring-1 ring-slate-300 ring-inset focus:ring-2 focus:ring-emerald-600')
<main class="mx-auto max-w-sm px-4 py-16">
    <h1 class="text-xl font-semibold tracking-tight">Sign in</h1>
    <p class="mt-1 text-sm text-slate-600">Internal area for uploading and curating datasets.</p>

    <form method="POST" action="{{ route('login.store') }}" class="mt-6 space-y-4 rounded-lg border border-slate-200 bg-white p-5" novalidate>
        @csrf

        @error('login')
            <p class="rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-800" role="alert">{{ $message }}</p>
        @enderror

        <div>
            <label for="login" class="text-sm font-medium text-slate-700">E-mail or username</label>
            <input id="login" name="login" type="text" value="{{ old('login') }}" required autofocus autocomplete="username" class="{{ $input }}">
        </div>
        <div>
            <label for="password" class="text-sm font-medium text-slate-700">Password</label>
            <input id="password" name="password" type="password" required autocomplete="current-password" class="{{ $input }}">
            @error('password')
                <p class="mt-1 text-xs text-rose-700">{{ $message }}</p>
            @enderror
        </div>

        <button type="submit" class="w-full rounded-md bg-emerald-700 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-800">Sign in</button>
    </form>

    <p class="mt-6 text-center text-sm text-slate-600">
        Looking for published data? <a href="{{ route('catalogue.index') }}" class="font-medium text-emerald-700 hover:underline">Open the catalogue</a>
    </p>
</main>
@endsection
