<?php

declare(strict_types=1);

namespace Libxa\Atlas\Schema;

/**
 * Turns a logical column type into DDL the connected database accepts.
 *
 * The Blueprint used to emit SQL directly, and the SQL it emitted was SQLite's:
 * `AUTOINCREMENT`, `LONGTEXT`, `DATETIME`, `BLOB`, backtick-quoted identifiers.
 * Migrations therefore ran on SQLite and failed on everything else — the first
 * column of the first table was already a syntax error on MySQL, reported at
 * line 2 because that is where the parser gave up.
 *
 * A migration describes intent. Which spelling that intent takes belongs here.
 */
abstract class Grammar
{
    public static function for(\PDO $pdo): self
    {
        $driver = (string) $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);

        return static::forDriver($driver);
    }

    public static function forDriver(string $driver): self
    {
        return match ($driver) {
            'mysql'  => new MySqlGrammar(),
            'pgsql'  => new PostgresGrammar(),
            'sqlite' => new SqliteGrammar(),
            default  => throw new \InvalidArgumentException(
                "No schema grammar for driver \"{$driver}\". Supported: mysql, pgsql, sqlite."
            ),
        };
    }

    /** Quote an identifier so a column called `order` or `group` still works. */
    abstract public function wrap(string $identifier): string;

    /** The whole definition of an auto-incrementing primary key column. */
    abstract public function increments(string $column): string;

    /**
     * The physical type for a logical one.
     *
     * @param array<string, mixed> $options length, total, places, values
     */
    abstract public function type(string $logical, array $options = []): string;

    /**
     * Whether UNSIGNED is a real modifier here.
     *
     * Only MySQL has it. Postgres rejects it outright, and SQLite ignores it
     * silently — which is worse, because the column then holds negatives that
     * the schema claims it cannot.
     */
    public function supportsUnsigned(): bool
    {
        return false;
    }

    /** Whether CREATE INDEX accepts IF NOT EXISTS. */
    public function supportsIndexIfNotExists(): bool
    {
        return true;
    }

    public function wrapAll(array $columns): string
    {
        return implode(', ', array_map([$this, 'wrap'], $columns));
    }

    /**
     * Whether this error means "it is already there".
     *
     * Used to tell a harmless repeat from a real failure. Swallowing every
     * error, which is what the schema builder used to do, makes a broken index
     * indistinguishable from one that already exists — so a migration reports
     * success and the index is simply absent.
     */
    public function isAlreadyExists(\Throwable $e): bool
    {
        $message = strtolower($e->getMessage());

        foreach (['already exists', 'duplicate column', 'duplicate key name', 'duplicate index'] as $needle) {
            if (str_contains($message, $needle)) {
                return true;
            }
        }

        return false;
    }
}
