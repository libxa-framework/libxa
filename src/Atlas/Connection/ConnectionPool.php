<?php

declare(strict_types=1);

namespace Libxa\Atlas\Connection;

/**
 * Atlas Connection Pool
 *
 * Manages PDO connections per driver. Supports read/write splitting.
 * Connections are reused within a worker process.
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
        // process: the classic "why is it still hitting the dev database".
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

    /**
     * The PDO extension that provides each driver.
     *
     * Used to tell an operator what to enable, rather than making them guess
     * that "pgsql" is spelled "pdo_pgsql" in php.ini.
     */
    public static function extensionFor(string $driver): string
    {
        return match (static::normalizeDriver($driver)) {
            'sqlite' => 'pdo_sqlite',
            'mysql'  => 'pdo_mysql',
            'pgsql'  => 'pdo_pgsql',
            default  => 'pdo_' . $driver,
        };
    }

    /**
     * Normalise the names people actually write.
     *
     * "postgres", "postgresql" and "mariadb" are what appear in .env files and
     * in every tutorial. Refusing them because the internal name differs is a
     * spelling test, not a safety check.
     */
    public static function normalizeDriver(string $driver): string
    {
        return match (strtolower(trim($driver))) {
            'postgres', 'postgresql', 'pgsql' => 'pgsql',
            'mariadb', 'mysql'                => 'mysql',
            'sqlite', 'sqlite3'               => 'sqlite',
            default                           => strtolower(trim($driver)),
        };
    }

    /** Drivers Atlas knows how to build a DSN for. */
    public const SUPPORTED = ['sqlite', 'mysql', 'pgsql'];

    protected function createConnection(array $config): \PDO
    {
        $driver  = static::normalizeDriver((string) ($config['driver'] ?? 'sqlite'));
        $options = [
            \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        if (! in_array($driver, self::SUPPORTED, true)) {
            throw new \InvalidArgumentException(sprintf(
                'Unsupported database driver "%s". Atlas supports: %s.',
                $driver,
                implode(', ', self::SUPPORTED),
            ));
        }

        // Checked before connecting, so a missing extension is reported as a
        // missing extension. PDO's own error for this is "could not find
        // driver" — six words naming neither the driver nor the fix.
        $this->assertDriverAvailable($driver);

        try {
            return match ($driver) {
                'sqlite' => $this->createSqlite($config, $options),
                'mysql'  => $this->createMysql($config, $options),
                'pgsql'  => $this->createPgsql($config, $options),
            };
        } catch (\PDOException $e) {
            throw new \RuntimeException($this->explainFailure($driver, $config, $e), 0, $e);
        }
    }

    protected function assertDriverAvailable(string $driver): void
    {
        $available = \PDO::getAvailableDrivers();

        if (! in_array($driver, $available, true)) {
            throw new DriverUnavailableException($driver, $available);
        }
    }

    /**
     * Say what we were trying to reach when the connection failed.
     *
     * A bare "Connection refused" does not say to which host, port or
     * database — which matters most when the answer turns out to be that the
     * config in use is not the config being edited.
     */
    protected function explainFailure(string $driver, array $config, \PDOException $e): string
    {
        $target = match ($driver) {
            'sqlite' => sprintf('the SQLite database at %s', $config['database'] ?? ':memory:'),
            default  => sprintf(
                '%s on %s:%s (database "%s", user "%s")',
                $driver,
                $config['host'] ?? '127.0.0.1',
                $config['port'] ?? static::defaultPort($driver),
                $config['database'] ?? '',
                $config['username'] ?? '',
            ),
        };

        $message = sprintf('Could not connect to %s: %s', $target, $e->getMessage());

        if ($hint = $this->portHint($driver, (string) ($config['port'] ?? ''))) {
            $message .= PHP_EOL . PHP_EOL . $hint;
        }

        return $message;
    }

    /**
     * Notice when the port belongs to a different database engine.
     *
     * DB_PORT is one setting shared by every driver, so switching
     * DB_CONNECTION from mysql to pgsql leaves the old port in place and the
     * connection fails with a transport error that never mentions the port.
     */
    protected function portHint(string $driver, string $port): ?string
    {
        if ($port === '') {
            return null;
        }

        $owners = ['3306' => 'mysql', '5432' => 'pgsql'];
        $owner  = $owners[$port] ?? null;

        if ($owner === null || $owner === $driver) {
            return null;
        }

        return sprintf(
            'Port %s is the default for %s, but this connection is %s (default %s).%sCheck DB_PORT — it is shared by every driver, so it keeps the old value when DB_CONNECTION changes.',
            $port,
            $owner,
            $driver,
            static::defaultPort($driver),
            PHP_EOL,
        );
    }

    public static function defaultPort(string $driver): string
    {
        return match (static::normalizeDriver($driver)) {
            'pgsql' => '5432',
            'mysql' => '3306',
            default => '',
        };
    }

    protected function createSqlite(array $config, array $options): \PDO
    {
        $database = (string) ($config['database'] ?? ':memory:');

        if ($database === ':memory:' || $database === '') {
            return new \PDO('sqlite::memory:', null, null, $options);
        }

        if (! self::isAbsolutePath($database)) {
            $app = \Libxa\Foundation\Application::getInstance();

            // 'database/foo.sqlite' and 'foo.sqlite' must land in the same
            // place: databasePath() already points at database/, so without
            // this the prefix is applied twice.
            $clean = str_starts_with($database, 'database/') || str_starts_with($database, 'database\\')
                ? substr($database, 9)
                : $database;

            $database = $app ? $app->databasePath($clean) : getcwd() . DIRECTORY_SEPARATOR . $clean;
        }

        $dir = dirname($database);

        if (! is_dir($dir) && ! mkdir($dir, 0755, true) && ! is_dir($dir)) {
            throw new \RuntimeException(sprintf(
                'Cannot create the directory for the SQLite database: %s',
                $dir,
            ));
        }

        return new \PDO("sqlite:$database", null, null, $options);
    }

    /**
     * Absolute-path test that works on Windows.
     *
     * A leading-slash check alone treats C:\srv\app.sqlite as relative and
     * re-roots it under the project's database directory, producing a path
     * like C:\proj\src\database\C:\srv\app.sqlite that cannot be created.
     */
    protected static function isAbsolutePath(string $path): bool
    {
        if ($path === '') {
            return false;
        }

        if ($path[0] === '/' || $path[0] === '\\') {
            return true;
        }

        return (bool) preg_match('#^[A-Za-z]:[\\\\/]#', $path);
    }

    protected function createMysql(array $config, array $options): \PDO
    {
        $host    = $config['host']     ?? '127.0.0.1';
        $port    = $config['port']     ?? '3306';
        $db      = $config['database'] ?? 'libxa';
        $charset = $config['charset']  ?? 'utf8mb4';
        $socket  = $config['unix_socket'] ?? null;

        $dsn = $socket
            ? "mysql:unix_socket=$socket;dbname=$db;charset=$charset"
            : "mysql:host=$host;port=$port;dbname=$db;charset=$charset";

        $pdo = new \PDO($dsn, $config['username'] ?? 'root', $config['password'] ?? '', $options);

        // SET NAMES cannot take a bound parameter, so the value is matched
        // against a whitelist rather than interpolated blind.
        if (preg_match('/^[A-Za-z0-9_]+$/', (string) $charset)) {
            $pdo->exec("SET NAMES $charset");
        }

        return $pdo;
    }

    protected function createPgsql(array $config, array $options): \PDO
    {
        $host = $config['host']     ?? '127.0.0.1';
        $port = $config['port']     ?? '5432';
        $db   = $config['database'] ?? 'libxa';

        $dsn = "pgsql:host=$host;port=$port;dbname=$db";

        if (! empty($config['sslmode'])) {
            $dsn .= ';sslmode=' . $config['sslmode'];
        }

        $pdo = new \PDO($dsn, $config['username'] ?? 'postgres', $config['password'] ?? '', $options);

        // Same reasoning as SET NAMES above.
        if (! empty($config['schema']) && preg_match('/^[A-Za-z0-9_, ]+$/', (string) $config['schema'])) {
            $pdo->exec('SET search_path TO ' . $config['schema']);
        }

        return $pdo;
    }

    /**
     * Build a connection config when nothing has been configured explicitly.
     *
     * The application's config/database.php is consulted first. Reading only
     * the environment here is what let a project edit config/database.php,
     * see no effect, and find nothing anywhere explaining why: every call site
     * uses the static getInstance(), which never runs the service provider
     * that would have loaded that file.
     */
    protected function resolveFromEnv(): array
    {
        $app = \Libxa\Foundation\Application::getInstance();

        if ($app !== null && ($fromConfig = $this->fromAppConfig($app)) !== null) {
            return $fromConfig;
        }

        // DB_CONNECTION is the documented name. DB_DRIVER is still accepted
        // because it shipped in .env.example for several releases, and
        // ignoring it would silently change which database an existing
        // project connects to on upgrade.
        $driver = $app?->env('DB_CONNECTION') ?? $app?->env('DB_DRIVER') ?? 'sqlite';
        $driver = static::normalizeDriver((string) $driver);

        return [
            'driver'   => $driver,
            'host'     => $app?->env('DB_HOST', '127.0.0.1'),
            'port'     => $app?->env('DB_PORT', static::defaultPort($driver)),
            'database' => $app?->env('DB_DATABASE', $driver === 'sqlite' ? 'database.sqlite' : 'libxa'),
            'username' => $app?->env('DB_USERNAME', $driver === 'pgsql' ? 'postgres' : 'root'),
            'password' => $app?->env('DB_PASSWORD', ''),
            'charset'  => 'utf8mb4',
        ];
    }

    /**
     * The connection config/database.php selects, if that file exists.
     *
     * @return array<string, mixed>|null
     */
    protected function fromAppConfig(\Libxa\Foundation\Application $app): ?array
    {
        $db = $app->config('database');

        if (! is_array($db) || empty($db['connections']) || ! is_array($db['connections'])) {
            return null;
        }

        $default = $db['default'] ?? null;

        if (! is_string($default) || $default === '') {
            return null;
        }

        $name       = static::normalizeDriver($default);
        $connection = $db['connections'][$default] ?? $db['connections'][$name] ?? null;

        if (! is_array($connection)) {
            // Naming a connection that does not exist is a config error, not
            // a reason to quietly fall back to SQLite and let the application
            // run against a different database than the one it asked for.
            throw new \RuntimeException(sprintf(
                'Database connection "%s" is selected but not defined in config/database.php. Defined: %s.',
                $default,
                implode(', ', array_keys($db['connections'])) ?: '(none)',
            ));
        }

        $connection['driver'] = static::normalizeDriver((string) ($connection['driver'] ?? $name));

        return $connection;
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
