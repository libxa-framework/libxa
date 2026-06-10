<?php

declare(strict_types=1);

namespace Libxa\Atlas\Schema;

/**
 * Schema Builder — entry point for DDL operations.
 */
class SchemaBuilder
{
    public function __construct(protected \PDO $pdo) {}

    public function create(string $table, \Closure $callback): void
    {
        $blueprint = new Blueprint($table, $this->pdo);
        $callback($blueprint);
        $blueprint->build();
    }

    /**
     * Add/modify columns on an existing table.
     */
    public function table(string $table, \Closure $callback): void
    {
        $blueprint = new Blueprint($table, $this->pdo, alter: true);
        $callback($blueprint);
        $blueprint->build();
    }

    public function drop(string $table): void
    {
        $this->pdo->exec("DROP TABLE IF EXISTS `$table`");
    }

    public function dropIfExists(string $table): void
    {
        $this->pdo->exec("DROP TABLE IF EXISTS `$table`");
    }

    public function hasTable(string $table): bool
    {
        $driver = $this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);

        return match ($driver) {
            'sqlite' => (bool) $this->pdo->query(
                "SELECT name FROM sqlite_master WHERE type='table' AND name=" . $this->pdo->quote($table)
            )?->fetch(),

            'mysql'  => (bool) $this->pdo->query(
                "SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = " . $this->pdo->quote($table)
            )?->fetch(),

            'pgsql'  => (bool) $this->pdo->query(
                "SELECT 1 FROM information_schema.tables WHERE table_schema = 'public' AND table_name = " . $this->pdo->quote($table)
            )?->fetch(),

            default  => false,
        };
    }

    public function hasColumn(string $table, string $column): bool
    {
        $driver = $this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);

        return match ($driver) {
            'sqlite' => in_array(
                $column,
                $this->pdo->query("PRAGMA table_info(`$table`)")?->fetchAll(\PDO::FETCH_COLUMN, 1) ?? []
            ),

            'mysql'  => (bool) $this->pdo->query(
                "SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = " .
                $this->pdo->quote($table) . " AND column_name = " . $this->pdo->quote($column)
            )?->fetch(),

            'pgsql'  => (bool) $this->pdo->query(
                "SELECT 1 FROM information_schema.columns WHERE table_schema = 'public' AND table_name = " .
                $this->pdo->quote($table) . " AND column_name = " . $this->pdo->quote($column)
            )?->fetch(),

            default  => false,
        };
    }
}
