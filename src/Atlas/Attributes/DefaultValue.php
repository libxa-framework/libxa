<?php

namespace Libxa\Atlas\Attributes;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class DefaultValue
{
    public function __construct(public readonly mixed $value) {}
}
