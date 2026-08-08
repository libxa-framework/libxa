<?php

declare(strict_types=1);

namespace Libxa\Router\Attributes;

/**
 * #[WsRoute('/channel-name')]
 * Mark a method as a WebSocket route handler.
 */
#[\Attribute(\Attribute::TARGET_METHOD)]
class WsRoute
{
    public function __construct(
        public readonly string $channel,
        public readonly string $name = '',
    ) {}
}
