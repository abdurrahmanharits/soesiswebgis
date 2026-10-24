<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Admin-only user management (no self-registration). Users are deactivated
 * rather than deleted so curation history keeps its accounts.
 */
class UserController extends Controller
{
    public function index(): View
    {
        return view('admin.users.index', [
            'users' => User::with('organization')->orderBy('name')->get(),
            'organizations' => Organization::withCount('users')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $user = User::create($data);

        return redirect()->route('admin.users.index')->with('status', "User {$user->username} created.");
    }

    public function edit(User $user): View
    {
        return view('admin.users.edit', [
            'user' => $user,
            'organizations' => Organization::orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $this->validated($request, $user);

        $this->guardAdminAccess($request->user(), $user, $data);

        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        $user->update($data);

        return redirect()->route('admin.users.index')->with('status', "User {$user->username} updated.");
    }

    private function validated(Request $request, ?User $user = null): array
    {
        // Stored lowercase, so uniqueness is case-insensitive.
        $request->merge([
            'username' => mb_strtolower(trim((string) $request->input('username'))),
            'email' => mb_strtolower(trim((string) $request->input('email'))),
        ]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'username' => ['required', 'regex:'.User::USERNAME_PATTERN, Rule::unique('users', 'username')->ignore($user)],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user)],
            'role' => ['required', Rule::in(User::ROLES)],
            'organization_id' => ['nullable', 'integer', Rule::exists('organizations', 'id')],
            'is_active' => ['sometimes', 'boolean'],
            // Required for new users; on edit, empty keeps the current password.
            'password' => [$user ? 'nullable' : 'required', 'confirmed', Password::min(8)],
        ], [
            'username.regex' => 'Use 3–30 lowercase letters, digits, dots or underscores.',
        ]);

        $data['is_active'] = $request->boolean('is_active', $user === null);

        return $data;
    }

    /** Keeps at least one active admin, and stops admins from locking themselves out. */
    private function guardAdminAccess(User $actor, User $user, array $data): void
    {
        $losesAdmin = $user->isAdmin() && $user->is_active && ($data['role'] !== 'admin' || ! $data['is_active']);

        if (! $losesAdmin) {
            return;
        }

        if ($actor->is($user)) {
            throw ValidationException::withMessages(['role' => 'You cannot remove your own admin access.']);
        }

        if (User::where('role', 'admin')->where('is_active', true)->count() <= 1) {
            throw ValidationException::withMessages(['role' => 'At least one active admin is required.']);
        }
    }
}
