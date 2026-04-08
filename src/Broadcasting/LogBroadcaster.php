<?php

declare(strict_types=1);

namespace Libxa\Broadcasting;

use Libxa\Foundation\Application;

class LogBroadcaster implements Broadcaster
{
    /**
     * Create a new LogBroadcaster instance.
     */
    public function __construct(protected Application $app)
    {
    }

    /**
     * Broadcast the given event to the channels by writing to logs.
     */
    public function broadcast(array $channels, string $event, array $payload): void
    {
        $message = sprintf(
            "----- [NexBroadcast-Log] -----\nChannels: %s\nEvent: %s\nPayload: %s\n-------------------------------",
            implode(', ', $channels),
            $event,
            json_encode($payload, JSON_PRETTY_PRINT)
        );

        file_put_contents(
            $this->app->storagePath('logs/broadcast.log'),
            $message . PHP_EOL . PHP_EOL,
            FILE_APPEND
        );
    }
}
