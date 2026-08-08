<?php

declare(strict_types=1);

namespace Libxa\Http\Middleware;

use Libxa\Http\Request;
use Libxa\Http\Response;

/**
 * CSRF protection for state-changing requests.
 *
 * Stability notes:
 *  - $request->input('_token') can be an array (an attacker just posts
 *    `_token[]=x`, or a form has two fields with the same name). Feeding that
 *    to hash_equals() raised a TypeError, turning a would-be 419 into an
 *    unauthenticated 500 on every form endpoint.
 *  - The X-CSRF-TOKEN header branch never worked, because Request::header()
 *    normalised the name differently from Request::capture(). That is fixed in
 *    Request; the encrypted X-XSRF-TOKEN cookie header is now accepted too, so
 *    SPA/axios clients work out of the box.
 *  - There was no way to exempt a URI. Webhook receivers (Stripe, GitHub,
 *    payment callbacks) cannot present a CSRF token, so every starter app had
 *    to delete this middleware wholesale to accept one.
 */
class CsrfMiddleware
{
    /**
     * URIs excluded from CSRF verification. Supports a trailing '*' wildcard.
     *
     * @var string[]
     */
    protected array $except = [];

    /**
     * Handle the incoming request.
     */
    public function handle(Request $request, \Closure $next): Response
    {
        if ($request->isMethodSafe() || $this->inExceptArray($request)) {
            return $next($request);
        }

        if ($this->tokensMatch($request)) {
            return $next($request);
        }

        abort(419, 'Page Expired');
    }

    protected function inExceptArray(Request $request): bool
    {
        $path = trim($request->path(), '/');

        foreach (array_merge($this->except, $this->configuredExceptions()) as $pattern) {
            $pattern = trim((string) $pattern, '/');

            if ($pattern === '') {
                continue;
            }

            if ($pattern === $path) {
                return true;
            }

            if (str_ends_with($pattern, '*')
                && str_starts_with($path . '/', rtrim(substr($pattern, 0, -1), '/') . '/')
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Exclusions declared in config/session.php under 'csrf_except'.
     *
     * @return string[]
     */
    protected function configuredExceptions(): array
    {
        $configured = config('session.csrf_except', []);

        return is_array($configured) ? $configured : [];
    }

    protected function tokensMatch(Request $request): bool
    {
        $token = $this->tokenFromRequest($request);

        if ($token === null || ! app()->has('session')) {
            return false;
        }

        $session = app('session')->token();

        return is_string($session) && $session !== '' && hash_equals($session, $token);
    }

    /**
     * Pull the token out of the request, rejecting anything non-string.
     */
    protected function tokenFromRequest(Request $request): ?string
    {
        foreach ([
            $request->input('_token'),
            $request->header('X-CSRF-TOKEN'),
            $this->decryptXsrfHeader($request->header('X-XSRF-TOKEN')),
        ] as $candidate) {
            if (is_string($candidate) && $candidate !== '') {
                return $candidate;
            }
        }

        return null;
    }

    protected function decryptXsrfHeader(string $header): ?string
    {
        if ($header === '' || ! app()->has('encrypter')) {
            return null;
        }

        try {
            $value = app('encrypter')->decrypt($header);

            return is_string($value) ? $value : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
