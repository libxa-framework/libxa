<?php

declare(strict_types=1);

namespace Libxa\Http\Middleware;

use Libxa\Http\Request;
use Libxa\Http\Response;

/**
 * Rate-Limiting (Throttle) Middleware
 *
 * Limits requests per IP using the file-based cache store.
 * Default: 60 requests per minute.
 */
class ThrottleMiddleware
{
    public function handle(Request $request, \Closure $next, int $maxAttempts = 60, int $decayMinutes = 1): Response
    {
        // Skip throttling if rate limiting is disabled
        if (env('RATE_LIMIT_ENABLED', 'true') === 'false') {
            return $next($request);
        }

        $key     = 'throttle:' . sha1($request->ip() . '|' . $request->path());
        $cache   = app()->has('cache') ? app('cache') : null;

        if ($cache === null) {
            return $next($request);
        }

        $hits = (int) $cache->get($key, 0);

        if ($hits >= $maxAttempts) {
            abort(429, 'Too Many Requests');
        }

        $cache->put($key, $hits + 1, $decayMinutes * 60);

        $response = $next($request);

        return $response
            ->withHeader('X-RateLimit-Limit', (string) $maxAttempts)
            ->withHeader('X-RateLimit-Remaining', (string) max(0, $maxAttempts - $hits - 1));
    }
}
