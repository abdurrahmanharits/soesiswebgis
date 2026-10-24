<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route-level role check, e.g. `role:admin` or `role:curator,admin`.
 * Answers 403 {"message": "Forbidden."} for API requests.
 */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (! in_array($request->user()?->role, $roles, true)) {
            abort(403, 'Forbidden.');
        }

        return $next($request);
    }
}
