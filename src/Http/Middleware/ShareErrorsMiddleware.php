<?php

declare(strict_types=1);

namespace Libxa\Http\Middleware;

use Libxa\Http\Request;
use Libxa\Http\Response;

/**
 * Share Errors Middleware
 *
 * Makes flash validation errors available to views.
 */
class ShareErrorsMiddleware
{
    public function handle(Request $request, \Closure $next): Response
    {
        if (app()->has('session')) {
            $session = app('session');

            // ageFlashData() is idempotent per request now; calling it here as
            // well as in SessionMiddleware used to blow away the flash bag
            // before any view could read it.
            $session->ageFlashData();

            // Make the error bag available to views without every controller
            // having to pass it, and without the @error directive needing to
            // reach into $_SESSION itself.
            $request->setAttribute('errors', errors());
        }

        return $next($request);
    }
}
