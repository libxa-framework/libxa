<?php

declare(strict_types=1);

namespace Libxa\Router\Attributes;

/**
 * #[Route('/path', 'GET')] or #[Route('/path', ['GET', 'POST'])]
 * Apply to controller methods to register routes automatically.
 *
 * Note: WsRoute, Middleware, Prefix, ApiController, Gate and Throttle used to
 * live in this same file. PSR-4 maps one class per file, so those classes were
 * unreachable by the autoloader: PHP threw "Attribute class ... not found"
 * the moment a controller actually used #[Prefix] or #[Middleware]. They now
 * each have their own file in this directory.
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
