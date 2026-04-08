<?php

declare(strict_types=1);

namespace Libxa\Atlas;

use Libxa\Atlas\Connection\ConnectionPool;

/**
 * DB Facade — static entry point for raw queries and Atlas ORM.
 *
 * Usage:
 *   DB::table('users')->where('active', true)->get();
 *   DB::select('SELECT * FROM users WHERE id = ?', [1]);
 *   DB::ask('users who registered this week');
 */
class DB
{
    protected static string $connection = 'default';

    public static function table(string $table): QueryBuilder
    {
        return new QueryBuilder(
            model:      \stdClass::class,
            table:      $table,
            connection: static::$connection,
        );
    }

    public static function connection(string $name): static
    {
        static::$connection = $name;
        return new static();
    }

    // ─────────────────────────────────────────────────────────────────
    //  Raw queries
    // ─────────────────────────────────────────────────────────────────

    public static function select(string $sql, array $bindings = []): array
    {
        $pdo  = ConnectionPool::getInstance()->get(static::$connection);
        $stmt = $pdo->prepare($sql);
        $stmt->execute($bindings);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public static function statement(string $sql, array $bindings = []): bool
    {
        $pdo  = ConnectionPool::getInstance()->get(static::$connection);
        $stmt = $pdo->prepare($sql);
        return $stmt->execute($bindings);
    }

    public static function insert(string $sql, array $bindings = []): int|false
    {
        $pdo  = ConnectionPool::getInstance()->get(static::$connection);
        $stmt = $pdo->prepare($sql);
        $ok   = $stmt->execute($bindings);
        return $ok ? (int) $pdo->lastInsertId() : false;
    }

    public static function update(string $sql, array $bindings = []): int
    {
        $pdo  = ConnectionPool::getInstance()->get(static::$connection);
        $stmt = $pdo->prepare($sql);
        $stmt->execute($bindings);
        return $stmt->rowCount();
    }

    public static function delete(string $sql, array $bindings = []): int
    {
        return static::update($sql, $bindings);
    }

    // ─────────────────────────────────────────────────────────────────
    //  Transactions
    // ─────────────────────────────────────────────────────────────────

    public static function transaction(\Closure $callback): mixed
    {
        $pdo = ConnectionPool::getInstance()->get(static::$connection);

        $pdo->beginTransaction();

        try {
            $result = $callback($pdo);
            $pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function beginTransaction(): void
    {
        ConnectionPool::getInstance()->get(static::$connection)->beginTransaction();
    }

    public static function commit(): void
    {
        ConnectionPool::getInstance()->get(static::$connection)->commit();
    }

    public static function rollBack(): void
    {
        ConnectionPool::getInstance()->get(static::$connection)->rollBack();
    }

    // ─────────────────────────────────────────────────────────────────
    //  AI Query Bridge
    // ─────────────────────────────────────────────────────────────────

    /**
     * Ask the AI a natural-language question.
     *
     *   $result = DB::ask("top 10 users by revenue last month");
     *   $result->sql   // Generated SQL
     *   $result->data  // Query results
     */
    public static function ask(string $question): AI\AiQueryResult
    {
        return AI\AiQueryBridge::ask($question);
    }

    /**
     * Generate a PHP scope from a natural-language description.
     * Returns ready-to-paste PHP code.
     */
    public static function generate(string $description): string
    {
        return AI\AiQueryBridge::generate($description);
    }

    // ─────────────────────────────────────────────────────────────────
    //  Schema
    // ─────────────────────────────────────────────────────────────────

    public static function schema(): Schema\SchemaBuilder
    {
        return new Schema\SchemaBuilder(
            ConnectionPool::getInstance()->get(static::$connection)
        );
    }

    public static function getPdo(): \PDO
    {
        return ConnectionPool::getInstance()->get(static::$connection);
    }

    public static function raw(string $value): RawExpression
    {
        return new RawExpression($value);
    }
}
