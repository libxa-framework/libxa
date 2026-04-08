<?php

namespace Libxa\Atlas\Attributes;

#[\Attribute(\Attribute::TARGET_CLASS)]
class OptimisticLock
{
    public function __construct(public readonly string $column = 'version') {}
}
