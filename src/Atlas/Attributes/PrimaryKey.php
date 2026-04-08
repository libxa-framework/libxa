<?php

namespace Libxa\Atlas\Attributes;

#[\Attribute(\Attribute::TARGET_CLASS)]
class PrimaryKey
{
    public function __construct(
        public readonly string $column       = 'id',
        public readonly bool   $incrementing = true,
    ) {}
}
