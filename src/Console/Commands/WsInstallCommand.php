<?php

declare(strict_types=1);

namespace Libxa\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Libxa\Foundation\Application;

/**
 * CLI command to install and scaffold the LibxaFrame WebSocket ecosystem.
 */
class WsInstallCommand extends Command
{
    protected static $defaultName = 'ws:install';

    public function __construct(protected Application $app)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('ws:install')
            ->setDescription('Installs and scaffolds the LibxaFrame WebSocket ecosystem');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('LibxaFrame WebSocket Installer');

        $this->setupEnvironment($io);
        $this->scaffoldDirectories($io);
        $this->scaffoldChannels($io);
        $this->scaffoldViews($io);
        $this->registerRoutes($io);

        $io->success('WebSocket ecosystem successfully installed!');
        $io->info('To start your servers:');
        $io->listing([
            'php Libxa ws:serve --port=8085',
            'php Libxa serve --port=8047',
            'Visit: http://localhost:8047/ws-test'
        ]);

        return Command::SUCCESS;
    }

    protected function setupEnvironment(SymfonyStyle $io): void
    {
        $envFile = $this->app->basePath('.env');
        if (file_exists($envFile)) {
            $content = file_get_contents($envFile);
            $modified = false;

            if (!str_contains($content, 'WS_PORT')) {
                $content .= "\n# WebSocket (Workerman)\nWS_HOST=0.0.0.0\nWS_PORT=8085\nWS_WORKERS=4\n";
                $modified = true;
            }

            if ($modified) {
                file_put_contents($envFile, $content);
                $io->comment('Configured WebSocket ports in .env');
            }
        }
    }

    protected function scaffoldDirectories(SymfonyStyle $io): void
    {
        $wsDir = $this->app->basePath('src/app/WebSockets');
        if (!is_dir($wsDir)) {
            mkdir($wsDir, 0755, true);
            $io->comment('Created src/app/WebSockets directory');
        }
    }

    protected function scaffoldChannels(SymfonyStyle $io): void
    {
        $channelFile = $this->app->basePath('src/app/WebSockets/RandomChannel.php');
        if (!file_exists($channelFile)) {
            $content = <<<'PHP'
<?php

declare(strict_types=1);

namespace App\WebSockets;

use Libxa\WebSockets\WsChannel;
use Libxa\WebSockets\WsConnection;
use Libxa\WebSockets\Attributes\WsRoute;
use Libxa\WebSockets\Attributes\OnEvent;

#[WsRoute('/ws/random')]
class RandomChannel extends WsChannel
{
    public function onOpen(WsConnection $connection): void
    {
        // Join the global random number stream room
        $connection->join('random-stream');
        
        $connection->send([
            'event' => 'connected',
            'message' => 'You are now receiving random numbers from the server!'
        ]);
    }

    #[OnEvent('ping')]
    public function handlePing(WsConnection $connection): void
    {
        $connection->send(['event' => 'pong', 'time' => time()]);
    }
}
PHP;
            file_put_contents($channelFile, $content);
            $io->comment('Scaffolded App\WebSockets\RandomChannel');
        }
    }

    protected function scaffoldViews(SymfonyStyle $io): void
    {
        $viewDir = $this->app->basePath('src/resources/views');
        if (!is_dir($viewDir)) {
            mkdir($viewDir, 0755, true);
        }

        $viewFile = $viewDir . '/ws-test.blade.php';
        if (!file_exists($viewFile)) {
            $content = <<<'HTML'
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WebSocket Test | LibxaFrame</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&family=JetBrains+Mono&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #0f172a;
            --card: #1e293b;
            --primary: #6366f1;
            --primary-glow: rgba(99, 102, 241, 0.5);
            --accent: #c084fc;
            --text: #f8fafc;
        }

        body {
            margin: 0;
            font-family: 'Inter', sans-serif;
            background: var(--bg);
            color: var(--text);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }

        .container {
            width: 100%;
            max-width: 500px;
            padding: 2rem;
        }

        .card {
            background: var(--card);
            border-radius: 1.5rem;
            padding: 2rem;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            border: 1px solid rgba(255, 255, 255, 0.1);
            text-align: center;
        }

        h1 { margin: 0 0 0.5rem; font-size: 1.5rem; font-weight: 700; }
        p { color: #94a3b8; font-size: 0.875rem; margin-bottom: 2rem; }

        .stat-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-item {
            background: rgba(0, 0, 0, 0.2);
            padding: 1.25rem;
            border-radius: 1rem;
            border: 1px solid rgba(255, 255, 255, 0.05);
        }

        .stat-label { font-size: 0.75rem; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.5rem; }
        .stat-value { font-family: 'JetBrains Mono', monospace; font-size: 1.25rem; font-weight: 600; color: var(--primary); }

        .pulse {
            animation: pulse-glow 2s infinite;
        }

        @keyframes pulse-glow {
            0% { text-shadow: 0 0 0px var(--primary-glow); }
            50% { text-shadow: 0 0 15px var(--primary-glow); }
            100% { text-shadow: 0 0 0px var(--primary-glow); }
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            background: rgba(239, 68, 68, 0.1);
            color: #f87171;
            margin-bottom: 1rem;
        }

        .status-online {
            background: rgba(34, 197, 94, 0.1);
            color: #4ade80;
        }

        .log-container {
            background: rgba(0, 0, 0, 0.3);
            border-radius: 0.75rem;
            padding: 1rem;
            text-align: left;
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.75rem;
            height: 120px;
            overflow-y: auto;
            color: #94a3b8;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div id="status" class="status-badge">Disconnected</div>
            <h1>WebSocket Pulse</h1>
            <p>Real-time data stream from LibxaFrame</p>

            <div class="stat-grid">
                <div class="stat-item">
                    <div class="stat-label">Random Value</div>
                    <div id="value" class="stat-value pulse">--</div>
                </div>
                <div class="stat-item">
                    <div class="stat-label">Server Time</div>
                    <div id="time" class="stat-value">--:--:--</div>
                </div>
            </div>

            <div id="log" class="log-container">
                <div>[System] Initializing connection...</div>
            </div>
        </div>
    </div>

    <script>
        const logEl = document.getElementById('log');
        const statusEl = document.getElementById('status');
        const valueEl = document.getElementById('value');
        const timeEl = document.getElementById('time');

        function log(msg) {
            const div = document.createElement('div');
            div.textContent = `[${new Date().toLocaleTimeString()}] ${msg}`;
            logEl.prepend(div);
        }

        const wsPort = 8085;
        const socket = new WebSocket(`ws://${window.location.hostname}:${wsPort}/ws/random`);

        socket.onopen = () => {
            statusEl.textContent = 'Online';
            statusEl.classList.add('status-online');
            log('Connected to WebSocket Server');
        };

        socket.onmessage = (event) => {
            const data = JSON.parse(event.data);
            if (data.event === 'random.number') {
                valueEl.textContent = data.data.value;
                timeEl.textContent = data.data.timestamp;
                log(`Pulse: ${data.data.value}`);
            }
        };

        socket.onclose = () => {
            statusEl.textContent = 'Disconnected';
            statusEl.classList.remove('status-online');
            log('Disconnected from server');
        };
    </script>
</body>
</html>
HTML;
            file_put_contents($viewFile, $content);
            $io->comment('Scaffolded src/resources/views/ws-test.blade.php');
        }
    }

    protected function registerRoutes(SymfonyStyle $io): void
    {
        $routeFile = $this->app->basePath('src/routes/web.php');
        if (file_exists($routeFile)) {
            $content = file_get_contents($routeFile);
            
            if (!str_contains($content, '/ws-test')) {
                $route = "\n// WebSocket Testing Dashboard\n\$router->get('/ws-test', function () {\n    return view('ws-test');\n});\n";
                file_put_contents($routeFile, $content . $route);
                $io->comment('Registered /ws-test route in src/routes/web.php');
            }
        }
    }
}
