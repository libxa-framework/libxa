<?php

declare(strict_types=1);

namespace Libxa\Router\Attributes;

/**
 * #[Middleware('auth')] or #[Middleware(['auth', 'throttle:60'])]
 * Apply middleware to a controller class or specific method.
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD)]
class Middleware
{
    public array $middleware;

    public function __construct(string|array $middleware)
    {
        $this->middleware = (array) $middleware;
    }
}
