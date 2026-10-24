<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Public catalogue pages are in Indonesian (lang/id.json); internal pages keep
 * the default English locale.
 */
class UsePublicLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        App::setLocale('id');

        return $next($request);
    }
}
