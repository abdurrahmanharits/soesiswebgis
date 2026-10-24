@extends('layouts.app')

@section('title', 'Edit user')

@section('content')
<main class="mx-auto max-w-3xl px-4 py-8 sm:px-6 lg:px-8">
    <a href="{{ route('admin.users.index') }}" class="text-sm font-medium text-slate-600 hover:text-slate-900">← Users</a>
    <h1 class="mt-4 text-2xl font-semibold tracking-tight">Edit {{ $user->name }}</h1>

    <form method="POST" action="{{ route('admin.users.update', $user) }}" class="mt-6 space-y-4 rounded-lg border border-slate-200 bg-white p-5">
        @csrf
        @method('PUT')
        @include('admin.users.partials.form', ['user' => $user])
        <div class="flex items-center gap-3">
            <button type="submit" class="rounded-md bg-emerald-700 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-800">Save changes</button>
            <a href="{{ route('admin.users.index') }}" class="text-sm font-medium text-slate-600 hover:text-slate-900">Cancel</a>
        </div>
    </form>
</main>
@endsection
