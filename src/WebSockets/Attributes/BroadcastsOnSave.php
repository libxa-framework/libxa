<?php

declare(strict_types=1);

namespace Libxa\WebSockets\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class BroadcastsOnSave
{
    public function __construct(
        public string $channel,
        public string $event,
        public array $fields = []
    ) {}
}
