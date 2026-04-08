<?php

declare(strict_types=1);

namespace Libxa\Broadcasting;

use Libxa\Foundation\Application;

/**
 * Libxa Broadcaster
 * 
 * Sends broadcast events to the Libxa WebSocket server via a local control socket.
 */
class LibxaBroadcaster implements Broadcaster
{
    public function __construct(
        protected Application $app,
        protected array $config = []
    ) {}

    /**
     * Broadcast the given event to the specified channels.
     */
    public function broadcast(array $channels, string $event, array $payload = []): void
    {
        // Prepare the command payload
        $command = json_encode([
            'command' => 'broadcast',
            'data'    => [
                'channels' => $channels,
                'event'    => $event,
                'payload'  => $payload,
            ]
        ], JSON_UNESCAPED_UNICODE);

        if (DIRECTORY_SEPARATOR === '\\') {
            // Windows: Use file-based bridge
            $path = storage_path('framework/ws_broadcasts');
            if (!is_dir($path)) {
                mkdir($path, 0777, true);
            }
            $filename = $path . '/' . microtime(true) . '_' . bin2hex(random_bytes(4)) . '.json';
            file_put_contents($filename, $command);
            return;
        }

        // Linux/Mac: Use socket bridge
        $host = $this->config['host'] ?? '127.0.0.1';
        $port = $this->config['port'] ?? (int) env('WS_INTERNAL_PORT', 8082);

        $socket = @fsockopen("tcp://{$host}", $port, $errno, $errstr, 2);

        if ($socket) {
            fwrite($socket, $command . "\n");
            fclose($socket);
        } else {
            error_log("LibxaBroadcaster: Could not connect to WebSocket control port at {$host}:{$port} ($errstr)");
        }
    }
}
