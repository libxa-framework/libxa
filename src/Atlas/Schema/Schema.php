<?php

declare(strict_types=1);

namespace Libxa\Atlas\Schema;

use Libxa\Atlas\Connection\ConnectionPool;

/**
 * Global Schema Proxy (Facade)
 * 
 * Proxies static calls to a SchemaBuilder instance.
 */
class Schema
{
    /** @var SchemaBuilder|null */
    protected static ?SchemaBuilder $instance = null;

    /**
     * Get the SchemaBuilder instance.
     */
    public static function getInstance(): SchemaBuilder
    {
        if (static::$instance === null) {
            $pdo = ConnectionPool::getInstance()->get();
            static::$instance = new SchemaBuilder($pdo);
        }
        return static::$instance;
    }

    /**
     * Handle static calls to the SchemaBuilder.
     */
    public static function __callStatic(string $method, array $args): mixed
    {
        return static::getInstance()->$method(...$args);
    }
}
