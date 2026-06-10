<?php

declare(strict_types=1);

namespace Libxa\Http\Middleware;

use Libxa\Http\Request;
use Libxa\Http\Response;

/**
 * Session Middleware
 *
 * Starts the session and ages flash data so it's available to views
 * on the current request, then clears it before the next request.
 */
class SessionMiddleware
{
    public function handle(Request $request, \Closure $next): Response
    {
        if (app()->has('session')) {
            $session = app('session');
            $session->start();
            // Age flash data: move 'next' → 'old' so this request's views can read it
            $session->ageFlashData();
        }

        return $next($request);
    }
}
