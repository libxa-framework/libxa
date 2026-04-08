<?php

namespace Libxa\Atlas\Attributes;

#[\Attribute(\Attribute::TARGET_CLASS)]
class SoftDeletes
{
    public function __construct(public readonly string $column = 'deleted_at') {}
}
