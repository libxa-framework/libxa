<?php

namespace Libxa\Atlas\Attributes;

#[\Attribute(\Attribute::TARGET_CLASS)]
class Table
{
    public function __construct(public readonly string $name) {}
}
