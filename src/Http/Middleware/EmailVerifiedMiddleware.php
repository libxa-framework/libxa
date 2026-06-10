<?php

declare(strict_types=1);

namespace Libxa\Http\Middleware;

use Libxa\Http\Request;
use Libxa\Http\Response;

/**
 * Email Verified Middleware
 *
 * Ensures the authenticated user has a verified email address.
 */
class EmailVerifiedMiddleware
{
    public function handle(Request $request, \Closure $next): Response
    {
        $user = auth()->user();

        if (! $user) {
            return redirect('/login');
        }

        // Check for email_verified_at attribute (null means not verified)
        if (isset($user->email_verified_at) && $user->email_verified_at === null) {
            if ($request->expectsJson()) {
                return json(['message' => 'Your email address is not verified.'], 403);
            }

            return redirect('/email/verify');
        }

        return $next($request);
    }
}
