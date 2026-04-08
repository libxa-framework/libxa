<?php

declare(strict_types=1);

namespace Libxa\Broadcasting;

use Libxa\Foundation\Application;

class PusherBroadcaster implements Broadcaster
{
    /**
     * Create a new PusherBroadcaster instance.
     * Note: In a production framework, this should ideally integrate with
     * the official `pusher/pusher-php-server` library.
     */
    public function __construct(protected Application $app, protected array $config)
    {
    }

    /**
     * Broadcast the given event to the channels using Pusher.
     */
    public function broadcast(array $channels, string $event, array $payload): void
    {
        // Simple cURL implementation for Pusher if library is not available
        // For now, we will mock the behavior as a lightweight built-in driver
        
        $logMessage = sprintf(
            "----- [NexBroadcast-Pusher-Mock] -----\nKey: %s\nChannels: %s\nEvent: %s\nPayload: %s\n-------------------------------",
            $this->config['key'] ?? 'unknown',
            implode(', ', $channels),
            $event,
            json_encode($payload, JSON_PRETTY_PRINT)
        );

        file_put_contents(
            $this->app->storagePath('logs/broadcast.log'),
            $logMessage . PHP_EOL . PHP_EOL,
            FILE_APPEND
        );
    }
}
