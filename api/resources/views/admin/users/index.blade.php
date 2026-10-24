@extends('layouts.app')

@section('title', 'Users')

@section('content')
<main class="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
    <div>
        <h1 class="text-2xl font-semibold tracking-tight">Users</h1>
        <p class="mt-1 text-sm text-slate-600">Accounts for the internal area. Uploaders upload and complete metadata of their own datasets; curators and admins approve, reject and reopen; admins manage users.</p>
    </div>

    @if (session('status'))
        <p class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm text-emerald-800" role="status">{{ session('status') }}</p>
    @endif

    <section class="overflow-hidden rounded-lg border border-slate-200 bg-white" aria-labelledby="users-heading">
        <h2 id="users-heading" class="border-b border-slate-200 px-5 py-3 text-sm font-semibold">All users</h2>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold tracking-wide text-slate-500 uppercase">
                    <tr>
                        <th class="px-5 py-2">Name</th>
                        <th class="px-5 py-2">Username / e-mail</th>
                        <th class="px-5 py-2">Role</th>
                        <th class="px-5 py-2">Organization</th>
                        <th class="px-5 py-2">Status</th>
                        <th class="px-5 py-2"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($users as $user)
                        <tr @class(['text-slate-400' => ! $user->is_active])>
                            <td class="px-5 py-3 font-medium {{ $user->is_active ? 'text-slate-900' : '' }}">{{ $user->name }}@if ($user->is(auth()->user())) <span class="text-xs font-normal text-slate-500">(you)</span>@endif</td>
                            <td class="px-5 py-3">{{ $user->username }}<br><span class="text-xs text-slate-500">{{ $user->email }}</span></td>
                            <td class="px-5 py-3">{{ $user->roleLabel() }}</td>
                            <td class="px-5 py-3">{{ $user->organization?->name ?? '—' }}</td>
                            <td class="px-5 py-3">
                                <span @class([
                                    'rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset',
                                    'bg-emerald-50 text-emerald-800 ring-emerald-200' => $user->is_active,
                                    'bg-slate-100 text-slate-600 ring-slate-300' => ! $user->is_active,
                                ])>{{ $user->is_active ? 'Active' : 'Inactive' }}</span>
                            </td>
                            <td class="px-5 py-3 text-right"><a href="{{ route('admin.users.edit', $user) }}" class="font-medium text-emerald-700 hover:underline">Edit</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-5 py-6 text-center text-slate-500">No users yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <div class="grid gap-6 lg:grid-cols-3">
        <section class="rounded-lg border border-slate-200 bg-white p-5 lg:col-span-2" aria-labelledby="create-heading">
            <h2 id="create-heading" class="text-sm font-semibold">Add user</h2>
            <form method="POST" action="{{ route('admin.users.store') }}" class="mt-4 space-y-4">
                @csrf
                @include('admin.users.partials.form', ['user' => null])
                <button type="submit" class="rounded-md bg-emerald-700 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-800">Create user</button>
            </form>
        </section>

        <section class="rounded-lg border border-slate-200 bg-white p-5" aria-labelledby="org-heading">
            <h2 id="org-heading" class="text-sm font-semibold">Organizations</h2>
            <p class="mt-1 text-xs text-slate-500">Recorded with each user's uploads for provenance. Does not limit access.</p>

            @error('name', 'organization')
                <p class="mt-3 text-xs text-rose-700">{{ $message }}</p>
            @enderror

            <ul class="mt-3 space-y-2">
                @foreach ($organizations as $organization)
                    <li>
                        <form method="POST" action="{{ route('admin.organizations.update', $organization) }}" class="flex items-center gap-2">
                            @csrf
                            @method('PUT')
                            <label class="min-w-0 flex-1">
                                <span class="sr-only">Organization name</span>
                                <input name="name" value="{{ $organization->name }}" maxlength="255" class="w-full rounded-md border-0 px-2.5 py-1.5 text-sm ring-1 ring-slate-300 ring-inset focus:ring-2 focus:ring-emerald-600">
                            </label>
                            <span class="w-14 shrink-0 text-right text-xs text-slate-500 tabular-nums">{{ $organization->users_count }} {{ $organization->users_count === 1 ? 'user' : 'users' }}</span>
                            <button type="submit" class="shrink-0 text-xs font-medium text-emerald-700 hover:underline">Rename</button>
                        </form>
                    </li>
                @endforeach
            </ul>

            <form method="POST" action="{{ route('admin.organizations.store') }}" class="mt-4 flex items-center gap-2 border-t border-slate-100 pt-4">
                @csrf
                <label class="min-w-0 flex-1">
                    <span class="sr-only">New organization</span>
                    <input name="name" maxlength="255" required placeholder="New organization" class="w-full rounded-md border-0 px-2.5 py-1.5 text-sm ring-1 ring-slate-300 ring-inset focus:ring-2 focus:ring-emerald-600">
                </label>
                <button type="submit" class="shrink-0 rounded-md px-3 py-1.5 text-xs font-medium text-slate-700 ring-1 ring-slate-300 ring-inset hover:bg-slate-50">Add</button>
            </form>
        </section>
    </div>
</main>
@endsection
