<?php

namespace Libxa\Atlas\Attributes;

#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD)]
class HasOne
{
    public function __construct(
        public readonly string  $related,
        public readonly string  $foreignKey = '',
        public readonly string  $localKey   = '',
    ) {}
}
