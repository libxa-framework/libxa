<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }} | LibxaPulse</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #030712;
            --glass: rgba(17, 24, 39, 0.7);
            --border: rgba(255, 255, 255, 0.1);
            --primary: #818cf8;
            --primary-glow: rgba(129, 140, 248, 0.5);
            --success: #34d399;
            --success-glow: rgba(52, 211, 153, 0.5);
            --accent: #f472b6;
            --text-main: #f3f4f6;
            --text-muted: #9ca3af;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background-color: var(--bg);
            background-image: 
                radial-gradient(circle at 0% 0%, rgba(129, 140, 248, 0.15) 0%, transparent 50%),
                radial-gradient(circle at 100% 100%, rgba(244, 114, 182, 0.1) 0%, transparent 50%);
            color: var(--text-main);
            font-family: 'Outfit', sans-serif;
            min-height: 100vh;
            overflow-x: hidden;
            display: flex;
            flex-direction: column;
        }

        header {
            padding: 2rem 5%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--border);
            backdrop-filter: blur(10px);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .logo {
            font-size: 1.5rem;
            font-weight: 700;
            letter-spacing: -0.025em;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .logo-box {
            width: 32px;
            height: 32px;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 0 15px var(--primary-glow);
        }

        .status-badge {
            background: var(--success-glow);
            color: var(--success);
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.875rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            border: 1px solid rgba(52, 211, 153, 0.2);
        }

        .status-dot {
            width: 8px;
            height: 8px;
            background: var(--success);
            border-radius: 50%;
            box-shadow: 0 0 10px var(--success);
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.2); opacity: 0.7; }
            100% { transform: scale(1); opacity: 1; }
        }

        main {
            padding: 2rem 5%;
            flex: 1;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
        }

        .card {
            background: var(--glass);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 1.5rem;
            backdrop-filter: blur(12px);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 2px;
            background: linear-gradient(90deg, transparent, var(--primary), transparent);
            opacity: 0;
            transition: opacity 0.3s;
        }

        .card:hover {
            transform: translateY(-5px);
            border-color: rgba(129, 140, 248, 0.3);
            box-shadow: 0 10px 30px -10px rgba(0, 0, 0, 0.5);
        }

        .card:hover::before {
            opacity: 1;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .card-title {
            font-size: 0.875rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-muted);
        }

        .card-icon {
            color: var(--primary);
            font-size: 1.25rem;
        }

        .value-large {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            font-family: 'JetBrains Mono', monospace;
        }

        .stat-footer {
            font-size: 0.875rem;
            color: var(--text-muted);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .progress-bar {
            height: 8px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 4px;
            margin-top: 1rem;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--primary), var(--accent));
            box-shadow: 0 0 10px var(--primary-glow);
            transition: width 0.5s ease;
        }

        .grid-full {
            grid-column: 1 / -1;
        }

        .table-container {
            width: 100%;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }

        th {
            text-align: left;
            padding: 1rem;
            font-size: 0.75rem;
            text-transform: uppercase;
            color: var(--text-muted);
            border-bottom: 1px solid var(--border);
        }

        td {
            padding: 1rem;
            border-bottom: 1px solid var(--border);
            font-size: 0.875rem;
        }

        .badge {
            padding: 0.125rem 0.5rem;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 600;
            background: rgba(255, 255, 255, 0.1);
        }

        footer {
            padding: 2rem 5%;
            text-align: center;
            font-size: 0.875rem;
            color: var(--text-muted);
            border-top: 1px solid var(--border);
        }

        .refresh-btn {
            background: var(--primary);
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .refresh-btn:hover {
            transform: scale(1.05);
            box-shadow: 0 0 20px var(--primary-glow);
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            main { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <header>
        <div class="logo">
            <div class="logo-box">NP</div>
            LibxaPulse
        </div>
        <div style="display: flex; gap: 1rem; align-items: center;">
            <div class="status-badge">
                <div class="status-dot"></div>
                System Active
            </div>
            <button class="refresh-btn" onclick="location.reload()">
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                </svg>
                Refresh
            </button>
        </div>
    </header>

    <main>
        <!-- Memory Card -->
        <div class="card">
            <div class="card-header">
                <span class="card-title">Memory Usage</span>
                <span class="card-icon">🧠</span>
            </div>
            <div class="value-large">{{ $stats['memory']['usage'] }}</div>
            <div class="stat-footer">Peak: {{ $stats['memory']['peak'] }}</div>
            <div class="progress-bar">
                <div class="progress-fill" style="width: <?php echo $stats['memory']['percentage']; ?>%"></div>
            </div>
        </div>

        <!-- CPU Card -->
        <div class="card">
            <div class="card-header">
                <span class="card-title">CPU Load</span>
                <span class="card-icon">⚡</span>
            </div>
            <div class="value-large">{{ number_format($stats['performance']['cpu_load'] * 100, 1) }}%</div>
            <div class="stat-footer">Load Average (1m)</div>
            <div class="progress-bar">
                <div class="progress-fill" style="width: <?php echo min(100, $stats['memory']['cpu_load'] * 100); ?>%"></div>
            </div>
        </div>

        <!-- Latency Card -->
        <div class="card">
            <div class="card-header">
                <span class="card-title">Last Execution</span>
                <span class="card-icon">⏱️</span>
            </div>
            <div class="value-large">{{ round($stats['performance']['execution'], 2) }} <span style="font-size: 1rem;">ms</span></div>
            <div class="stat-footer">Runtime Latency</div>
        </div>

        <!-- System Details Card -->
        <div class="card grid-full">
            <div class="card-header">
                <span class="card-title">Framework & Environment</span>
                <span class="card-icon">🌍</span>
            </div>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Parameter</th>
                            <th>Value</th>
                            <th>Info</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>LibxaFrame Version</td>
                            <td><span class="badge">{{ $stats['framework']['version'] }}</span></td>
                            <td>Latest Build</td>
                        </tr>
                        <tr>
                            <td>PHP Version</td>
                            <td>{{ $stats['system']['php_version'] }}</td>
                            <td>SAPI: {{ php_sapi_name() }}</td>
                        </tr>
                        <tr>
                            <td>Environment</td>
                            <td><span class="badge" style="background: var(--primary-glow); color: var(--primary);">{{ $stats['framework']['environment'] }}</span></td>
                            <td>Context: {{ $stats['framework']['context'] }}</td>
                        </tr>
                        <tr>
                            <td>Server OS</td>
                            <td>{{ $stats['system']['os'] }}</td>
                            <td>{{ $stats['system']['server_time'] }} UTC</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Real-time Console Log (Simulated with WebSocket data in future) -->
        <div class="card grid-full" style="background: rgba(0, 0, 0, 0.3);">
            <div class="card-header">
                <span class="card-title">Real-time Hook Stream</span>
                <span class="card-icon">📜</span>
            </div>
            <div id="hook-stream" style="font-family: 'JetBrains Mono', monospace; font-size: 0.8rem; color: var(--success); height: 150px; overflow-y: auto;">
                [Pulse] Monitoring Hook System for model events...<br>
                [Pulse] Connecting to primary database connection [{{ app()->env('DB_CONNECTION', 'default') }}]...<br>
                [Pulse] Monitoring WebSocket port [{{ app()->env('WS_PORT', '8085') }}]...<br>
                [Pulse] Monitoring Cache engine [{{ app()->env('CACHE_DRIVER', 'file') }}]...<br>
                <span style="color: var(--text-muted)">Ready for real-time telemetry.</span>
            </div>
        </div>
    </main>

    <footer>
        &copy; {{ date('Y') }} LibxaFrame Project — Crafted for Developers.
    </footer>

    <script>
        // Future: Add WebSocket connection to real-time stats endpoint
        function refreshStats() {
            // fetch('/pulse/api').then(...)
            console.log("LibxaPulse: Refreshing system telemetry...");
        }

        // Auto-refresh every 30 seconds
        setInterval(() => {
            // Silent refresh or full page reload for now
            // location.reload();
        }, 30000);
    </script>
</body>
</html>
