<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Session login with e-mail or username. Errors never reveal whether an
 * account exists or is deactivated.
 */
class LoginController extends Controller
{
    public const MAX_ATTEMPTS = 5;

    private const INVALID = 'The provided credentials are invalid.';

    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'login' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ]);

        $login = mb_strtolower(trim($credentials['login']));
        $key = 'login:'.sha1($login.'|'.$request->ip());

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            throw ValidationException::withMessages([
                'login' => 'Too many login attempts. Please try again in '.RateLimiter::availableIn($key).' seconds.',
            ]);
        }

        $user = User::query()
            ->where(str_contains($login, '@') ? 'email' : 'username', $login)
            ->first();

        // Hash::check runs even without a user, so response time does not reveal existence.
        $valid = Hash::check($credentials['password'], $user?->password ?? '$2y$12$'.Str::random(53));

        if (! $user || ! $valid || ! $user->is_active) {
            RateLimiter::hit($key, 60);

            throw ValidationException::withMessages(['login' => self::INVALID]);
        }

        RateLimiter::clear($key);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended('/datasets');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
