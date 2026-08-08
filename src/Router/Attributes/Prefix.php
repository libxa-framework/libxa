<?php

declare(strict_types=1);

namespace Libxa\Router\Attributes;

/**
 * #[Prefix('/api/v1')]
 * Apply a URI prefix to all routes in a controller class.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class Prefix
{
    public function __construct(public readonly string $prefix) {}
}
