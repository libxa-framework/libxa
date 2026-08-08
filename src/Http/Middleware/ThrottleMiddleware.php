<?php

declare(strict_types=1);

namespace Libxa\Http\Middleware;

use Libxa\Http\Request;
use Libxa\Http\Response;

/**
 * Rate-Limiting (Throttle) Middleware
 *
 * Limits requests per IP using the configured cache store.
 * Default: 60 requests per minute.
 *
 * Stability notes:
 *  - The parameters are typed int|string. The pipeline parses "throttle:60"
 *    into the string "60", and since both Pipeline.php and this file declare
 *    strict_types=1, passing that string to an `int` parameter raised an
 *    uncatchable TypeError — meaning the whole built-in 'api' middleware
 *    group crashed on the first request.
 *  - The decay window is anchored on the first hit. Re-putting the counter
 *    with a fresh TTL on every request meant that under sustained traffic the
 *    window never expired and a client stayed locked out indefinitely.
 *  - A blocked request now carries Retry-After / X-RateLimit-* headers.
 */
class ThrottleMiddleware
{
    public function handle(
        Request $request,
        \Closure $next,
        int|string $maxAttempts = 60,
        int|string $decayMinutes = 1
    ): Response {
        $maxAttempts  = max(1, (int) $maxAttempts);
        $decaySeconds = max(1, (int) $decayMinutes) * 60;

        if (! \Libxa\Foundation\Application::envBool('RATE_LIMIT_ENABLED', true)) {
            return $next($request);
        }

        if (! app()->has('cache')) {
            return $next($request);
        }

        $cache = app('cache');

        $key       = 'throttle:' . sha1($request->ip() . '|' . $request->path());
        $resetKey  = $key . ':reset';

        $hits    = (int) $cache->get($key, 0);
        $resetAt = (int) $cache->get($resetKey, 0);

        // Start a fresh window when there is none, or the old one has passed.
        if ($hits === 0 || $resetAt <= time()) {
            $hits    = 0;
            $resetAt = time() + $decaySeconds;
            $cache->put($resetKey, $resetAt, $decaySeconds);
        }

        if ($hits >= $maxAttempts) {
            $retryAfter = max(1, $resetAt - time());

            throw new \Libxa\Http\Exceptions\HttpException(
                429,
                'Too Many Requests',
                headers: [
                    'Retry-After'           => (string) $retryAfter,
                    'X-RateLimit-Limit'     => (string) $maxAttempts,
                    'X-RateLimit-Remaining' => '0',
                ]
            );
        }

        // TTL tracks the remaining window, not a fresh full window.
        $cache->put($key, $hits + 1, max(1, $resetAt - time()));

        $response = $next($request);

        return $response
            ->withHeader('X-RateLimit-Limit', (string) $maxAttempts)
            ->withHeader('X-RateLimit-Remaining', (string) max(0, $maxAttempts - $hits - 1));
    }
}
