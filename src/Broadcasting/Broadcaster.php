<?php

declare(strict_types=1);

namespace Libxa\Broadcasting;

interface Broadcaster
{
    /**
     * Broadcast the given event to the channels.
     */
    public function broadcast(array $channels, string $event, array $payload): void;
}
