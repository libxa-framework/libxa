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
        // Age flash data so @error directives work in views
        if (app()->has('session')) {
            app('session')->ageFlashData();
        }

        return $next($request);
    }
}
