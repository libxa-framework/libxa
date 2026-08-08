<?php

declare(strict_types=1);

namespace Libxa\Atlas\Schema;

use Libxa\Atlas\Connection\ConnectionPool;

/**
 * Global Schema Proxy (Facade)
 *
 * Proxies static calls to a SchemaBuilder instance.
 *
 * Stability note: the builder used to be memoised in a static that nothing
 * ever invalidated. It captured whatever PDO the connection pool happened to
 * hold the first time Schema was touched, and kept using it for the rest of
 * the process — so after ConnectionPool::configure() (or a reconnect, or a
 * tenant switch, or the next test's database) every Schema:: call still wrote
 * DDL to the *previous* connection. The builder is now rebuilt whenever the
 * underlying PDO handle changes.
 */
class Schema
{
    protected static ?SchemaBuilder $instance = null;

    /** The PDO the cached builder was created for. */
    protected static ?\PDO $boundPdo = null;

    /** The connection name the cached builder was created for. */
    protected static string $boundConnection = 'default';

    /**
     * Get the SchemaBuilder instance for a connection.
     */
    public static function getInstance(string $connection = 'default'): SchemaBuilder
    {
        $pdo = ConnectionPool::getInstance()->get($connection);

        if (static::$instance === null
            || static::$boundPdo !== $pdo
            || static::$boundConnection !== $connection
        ) {
            static::$instance        = new SchemaBuilder($pdo);
            static::$boundPdo        = $pdo;
            static::$boundConnection = $connection;
        }

        return static::$instance;
    }

    /**
     * Build against a specific connection.
     */
    public static function connection(string $name): SchemaBuilder
    {
        return static::getInstance($name);
    }

    /**
     * Drop the cached builder (test isolation, worker restarts).
     */
    public static function reset(): void
    {
        static::$instance        = null;
        static::$boundPdo        = null;
        static::$boundConnection = 'default';
    }

    /**
     * Handle static calls to the SchemaBuilder.
     */
    public static function __callStatic(string $method, array $args): mixed
    {
        return static::getInstance()->$method(...$args);
    }
}
