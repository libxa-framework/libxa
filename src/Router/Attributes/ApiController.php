<?php

declare(strict_types=1);

namespace Libxa\Router\Attributes;

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
