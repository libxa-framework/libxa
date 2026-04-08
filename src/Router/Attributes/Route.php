<?php

declare(strict_types=1);

namespace Libxa\Router\Attributes;

/**
 * #[Route('/path', 'GET')] or #[Route('/path', ['GET', 'POST'])]
 * Apply to controller methods to register routes automatically.
 */
#[\Attribute(\Attribute::TARGET_METHOD)]
class Route
{
    public array $methods;

    public function __construct(
        public readonly string       $uri,
        string|array                 $methods = 'GET',
        public readonly string       $name    = '',
    ) {
        $this->methods = array_map('strtoupper', (array) $methods);
    }
}

/**
 * #[WsRoute('/channel-name')]
 * Mark a method as a WebSocket route handler.
 */
#[\Attribute(\Attribute::TARGET_METHOD)]
class WsRoute
{
    public function __construct(
        public readonly string $channel,
        public readonly string $name = '',
    ) {}
}

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

/**
 * #[Prefix('/api/v1')]
 * Apply a URI prefix to all routes in a controller class.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class Prefix
{
    public function __construct(public readonly string $prefix) {}
}

/**
 * #[ApiController]
 * Marks the controller as an API controller (auto-applies JSON responses).
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class ApiController
{
    public function __construct(
        public readonly string $prefix = '/api',
    ) {}
}

/**
 * #[Gate('permission-name')]
 * Apply authorization gate check to a route.
 */
#[\Attribute(\Attribute::TARGET_METHOD)]
class Gate
{
    public function __construct(public readonly string $ability) {}
}

/**
 * #[Throttle('60/minute')]
 * Apply rate limiting to a route.
 */
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::TARGET_CLASS)]
class Throttle
{
    public function __construct(
        public readonly string $limit = '60/minute',
    ) {}
}
