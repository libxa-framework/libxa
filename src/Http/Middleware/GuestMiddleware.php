<?php

declare(strict_types=1);

namespace Libxa\Http\Middleware;

use Libxa\Http\Request;
use Libxa\Http\Response;

class GuestMiddleware
{
    /**
     * Handle the incoming request.
     * Redirects authenticated users away from guest-only pages (e.g. login).
     */
    public function handle(Request $request, \Closure $next, ?string $guard = null): Response
    {
        if (app('auth')->guard($guard)->check()) {
            return redirect('/home');
        }

        return $next($request);
    }
}
