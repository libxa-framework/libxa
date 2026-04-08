<?php

declare(strict_types=1);

namespace Libxa\Http\Middleware;

use Libxa\Http\Request;
use Libxa\Http\Response;

class AuthMiddleware
{
    /**
     * Handle the incoming request.
     */
    public function handle(Request $request, \Closure $next, ?string $guard = null): Response
    {
        if (app('auth')->guard($guard)->guest()) {
            if ($request->expectsJson() || $guard === 'api' || $guard === 'sanctum') {
                return json(['message' => 'Unauthenticated.'], 401);
            }

            return redirect('/login');
        }

        return $next($request);
    }
}
