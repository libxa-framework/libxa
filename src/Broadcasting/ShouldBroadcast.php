<?php

declare(strict_types=1);

namespace Libxa\Broadcasting;

interface ShouldBroadcast
{
    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array;

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array;

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string;
}
