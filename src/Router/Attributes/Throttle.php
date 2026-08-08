<?php

declare(strict_types=1);

namespace Libxa\Router\Attributes;

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
