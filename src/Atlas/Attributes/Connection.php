<?php

namespace Libxa\Atlas\Attributes;

#[\Attribute(\Attribute::TARGET_CLASS)]
class Connection
{
    public function __construct(public readonly string $name = 'default') {}
}
