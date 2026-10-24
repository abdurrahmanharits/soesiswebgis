{{-- Shared create/edit fields. $user is null when creating. --}}
@php
    $input = 'mt-1 w-full rounded-md border-0 px-3 py-2 text-sm ring-1 ring-slate-300 ring-inset focus:ring-2 focus:ring-emerald-600';
    $label = 'text-sm font-medium text-slate-700';
    $error = 'mt-1 text-xs text-rose-700';
@endphp

<div class="grid gap-4 sm:grid-cols-2">
    <div>
        <label for="name" class="{{ $label }}">Name</label>
        <input id="name" name="name" type="text" maxlength="100" required value="{{ old('name', $user?->name) }}" class="{{ $input }}">
        @error('name') <p class="{{ $error }}">{{ $message }}</p> @enderror
    </div>
    <div>
        <label for="username" class="{{ $label }}">Username</label>
        <input id="username" name="username" type="text" maxlength="30" required autocomplete="off" value="{{ old('username', $user?->username) }}" class="{{ $input }}" placeholder="e.g. rina.pratiwi">
        <p class="mt-1 text-xs text-slate-500">3–30 lowercase letters, digits, dots or underscores.</p>
        @error('username') <p class="{{ $error }}">{{ $message }}</p> @enderror
    </div>
    <div>
        <label for="email" class="{{ $label }}">E-mail</label>
        <input id="email" name="email" type="email" maxlength="255" required autocomplete="off" value="{{ old('email', $user?->email) }}" class="{{ $input }}">
        @error('email') <p class="{{ $error }}">{{ $message }}</p> @enderror
    </div>
    <div>
        <label for="role" class="{{ $label }}">Role</label>
        <select id="role" name="role" class="{{ $input }} pr-8">
            @foreach (\App\Models\User::ROLE_LABELS as $value => $text)
                <option value="{{ $value }}" @selected(old('role', $user?->role ?? 'uploader') === $value)>{{ $text }}</option>
            @endforeach
        </select>
        @error('role') <p class="{{ $error }}">{{ $message }}</p> @enderror
    </div>
    <div>
        <label for="organization_id" class="{{ $label }}">Organization</label>
        <select id="organization_id" name="organization_id" class="{{ $input }} pr-8">
            <option value="">—</option>
            @foreach ($organizations as $organization)
                <option value="{{ $organization->id }}" @selected((string) old('organization_id', $user?->organization_id) === (string) $organization->id)>{{ $organization->name }}</option>
            @endforeach
        </select>
        @error('organization_id') <p class="{{ $error }}">{{ $message }}</p> @enderror
    </div>
    <div class="flex items-end">
        <label class="inline-flex items-center gap-2 pb-2 text-sm text-slate-700">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $user?->is_active ?? true)) class="size-4 accent-emerald-700">
            Active (can sign in)
        </label>
    </div>
    <div>
        <label for="password" class="{{ $label }}">{{ $user ? 'New password' : 'Password' }}</label>
        <input id="password" name="password" type="password" autocomplete="new-password" @required(! $user) class="{{ $input }}">
        <p class="mt-1 text-xs text-slate-500">{{ $user ? 'Leave empty to keep the current password.' : 'At least 8 characters.' }}</p>
        @error('password') <p class="{{ $error }}">{{ $message }}</p> @enderror
    </div>
    <div>
        <label for="password_confirmation" class="{{ $label }}">Repeat password</label>
        <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" @required(! $user) class="{{ $input }}">
    </div>
</div>
