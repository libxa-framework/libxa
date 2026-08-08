<?php

declare(strict_types=1);

namespace Libxa\Router\Attributes;

/**
 * #[Gate('permission-name')]
 * Apply authorization gate check to a route.
 */
#[\Attribute(\Attribute::TARGET_METHOD)]
class Gate
{
    public function __construct(public readonly string $ability) {}
}
