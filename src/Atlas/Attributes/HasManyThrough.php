<?php

namespace Libxa\Atlas\Attributes;

#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD)]
class HasManyThrough
{
    public function __construct(
        public readonly string $related,
        public readonly string $through,
        public readonly string $firstKey  = '',
        public readonly string $secondKey = '',
    ) {}
}
