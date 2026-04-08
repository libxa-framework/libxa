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

    public function table(string $table, \Closure $callback): void
    {
        // ALTER TABLE support would go here
        $blueprint = new Blueprint($table, $this->pdo);
        $callback($blueprint);
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
        $result = $this->pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='$table'");
        return $result && $result->fetch() !== false;
    }

    public function hasColumn(string $table, string $column): bool
    {
        $stmt = $this->pdo->query("PRAGMA table_info(`$table`)");
        $cols = $stmt ? $stmt->fetchAll(\PDO::FETCH_COLUMN, 1) : [];
        return in_array($column, $cols);
    }
}
