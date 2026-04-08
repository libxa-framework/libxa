<?php

declare(strict_types=1);

namespace Libxa\Http\Middleware;

use Libxa\Http\Request;
use Libxa\Http\Response;

class CsrfMiddleware
{
    /**
     * Handle the incoming request.
     */
    public function handle(Request $request, \Closure $next): Response
    {
        if ($request->isMethodSafe()) {
            return $next($request);
        }

        if ($this->tokensMatch($request)) {
            return $next($request);
        }

        abort(419, 'Page Expired');
    }

    protected function tokensMatch(Request $request): bool
    {
        $token = $request->input('_token') ?: $request->header('X-CSRF-TOKEN');

        if (! $token || ! app()->has('session')) {
            return false;
        }

        return hash_equals(app('session')->token(), $token);
    }
}
