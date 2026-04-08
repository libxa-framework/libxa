<?php

declare(strict_types=1);

namespace Libxa\Http\Middleware;

use Libxa\Http\Request;
use Libxa\Http\Response;

class SessionMiddleware
{
    /**
     * Handle the incoming request.
     */
    public function handle(Request $request, \Closure $next): Response
    {
        // Start session if not started
        if (app()->has('session')) {
            app('session')->start();
        }

        return $next($request);
    }
}
