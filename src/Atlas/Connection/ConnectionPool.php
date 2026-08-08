<?php

declare(strict_types=1);

namespace Libxa\Atlas\Connection;

/**
 * Atlas Connection Pool
 *
 * Manages PDO connections per driver. Supports read/write splitting.
 * Workerman-friendly: connections are reused within a worker process.
 */
class ConnectionPool
{
    protected static ?self $instance = null;
    protected array $connections = [];
    protected array $configs     = [];

    public static function getInstance(): static
    {
        if (static::$instance === null) {
            static::$instance = new static();
        }
        return static::$instance;
    }

    public function configure(array $configs): void
    {
        $this->configs = $configs;

        // Drop live handles so the new configuration actually takes effect.
        // Without this, calling configure() after something had already
        // resolved 'default' (which the boot order makes easy) left the pool
        // serving connections built from the *old* config for the rest of the
        // process — the classic "why is it still hitting the dev database".
        $this->connections = [];
    }

    /**
     * Inject a ready-made PDO handle.
     *
     * Needed by tests and by tooling that has to run against an in-memory
     * database: previously the only way in was through configure(), so there
     * was no way to share a single `sqlite::memory:` handle (each new
     * connection to :memory: is a brand-new, empty database).
     */
    public function setConnection(string $name, \PDO $pdo): void
    {
        $this->connections[$name] = $pdo;
    }

    /**
     * Close all pooled connections and forget the configuration.
     */
    public function reset(): void
    {
        $this->connections = [];
        $this->configs     = [];
    }

    /**
     * Discard the process-wide singleton (test isolation, worker restarts).
     */
    public static function resetInstance(): void
    {
        static::$instance = null;
    }

    /**
     * Whether a connection has already been established.
     */
    public function isConnected(string $name = 'default'): bool
    {
        return isset($this->connections[$name]);
    }

    /**
     * Get (or create) a PDO connection by name.
     */
    public function get(string $name = 'default'): \PDO
    {
        if (isset($this->connections[$name])) {
            return $this->connections[$name];
        }

        $config = $this->configs[$name] ?? $this->resolveFromEnv();

        $pdo = $this->createConnection($config);
        $this->connections[$name] = $pdo;

        return $pdo;
    }

    protected function createConnection(array $config): \PDO
    {
        $driver   = $config['driver'] ?? 'sqlite';
        $options  = [
            \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        return match ($driver) {
            'sqlite' => $this->createSqlite($config, $options),
            'mysql'  => $this->createMysql($config, $options),
            'pgsql'  => $this->createPgsql($config, $options),
            default  => throw new \InvalidArgumentException("Unsupported DB driver: $driver"),
        };
    }

    protected function createSqlite(array $config, array $options): \PDO
    {
        $database = $config['database'] ?? ':memory:';

        // Relative paths resolved from base path
        if ($database !== ':memory:' && ! str_starts_with($database, '/')) {
            $app = \Libxa\Foundation\Application::getInstance();
            
            // If the database starts with 'database/', remove that prefix before joining with databasePath()
            $cleanPath = str_starts_with($database, 'database/') 
                ? substr($database, 9) 
                : $database;

            $database = $app ? $app->databasePath($cleanPath) : getcwd() . '/' . $database;
        }

        // Create directory if needed
        $dir = dirname($database);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        return new \PDO("sqlite:$database", null, null, $options);
    }

    protected function createMysql(array $config, array $options): \PDO
    {
        $host    = $config['host']     ?? '127.0.0.1';
        $port    = $config['port']     ?? '3306';
        $db      = $config['database'] ?? 'Libxaframe';
        $charset = $config['charset']  ?? 'utf8mb4';

        $dsn = "mysql:host=$host;port=$port;dbname=$db;charset=$charset";

        $pdo = new \PDO($dsn, $config['username'] ?? 'root', $config['password'] ?? '', $options);
        $pdo->exec("SET NAMES $charset");

        return $pdo;
    }

    protected function createPgsql(array $config, array $options): \PDO
    {
        $host = $config['host']     ?? '127.0.0.1';
        $port = $config['port']     ?? '5432';
        $db   = $config['database'] ?? 'Libxaframe';

        $dsn = "pgsql:host=$host;port=$port;dbname=$db";

        return new \PDO($dsn, $config['username'] ?? 'postgres', $config['password'] ?? '', $options);
    }

    protected function resolveFromEnv(): array
    {
        $app = \Libxa\Foundation\Application::getInstance();

        return [
            'driver'   => $app?->env('DB_DRIVER',   'sqlite'),
            'host'     => $app?->env('DB_HOST',     '127.0.0.1'),
            'port'     => $app?->env('DB_PORT',     '3306'),
            'database' => $app?->env('DB_DATABASE', 'database/database.sqlite'),
            'username' => $app?->env('DB_USERNAME', 'root'),
            'password' => $app?->env('DB_PASSWORD', ''),
            'charset'  => 'utf8mb4',
        ];
    }

    public function disconnect(string $name = 'default'): void
    {
        unset($this->connections[$name]);
    }

    public function disconnectAll(): void
    {
        $this->connections = [];
    }
}
