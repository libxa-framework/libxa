<?php

declare(strict_types=1);

namespace Libxa\Atlas\Schema;

/**
 * Column definition with fluent modifiers.
 *
 * Holds the *logical* type — 'string', 'json', 'dateTime' — and asks the
 * grammar for its spelling only when the SQL is built. That is what lets one
 * migration run against SQLite, MySQL and Postgres.
 */
class ColumnDefinition
{
    protected bool    $isNullable = false;
    protected mixed   $default    = '\0NONE\0';
    protected bool    $isUnique   = false;
    protected bool    $unsigned   = false;

    /**
     * @param array<string, mixed> $options length, total, places, values
     */
    public function __construct(
        protected string    $name,
        protected string    $logicalType,
        protected Blueprint $blueprint,
        protected array     $options = [],
    ) {}

    public function nullable(): static  { $this->isNullable = true; return $this; }
    public function unsigned(): static  { $this->unsigned    = true; return $this; }

    public function default(mixed $value): static
    {
        $this->default = $value;
        return $this;
    }

    public function unique(): static
    {
        $this->blueprint->unique($this->name);
        return $this;
    }

    public function index(): static
    {
        $this->blueprint->index($this->name);
        return $this;
    }

    public function constrained(?string $table = null, string $column = 'id'): ForeignKeyDefinition
    {
        $table = $table ?? str_replace('_id', 's', $this->name);
        return $this->blueprint->foreign($this->name)->references($column)->on($table);
    }

    public function name(): string
    {
        return $this->name;
    }

    public function toSql(?Grammar $grammar = null): string
    {
        $grammar ??= $this->blueprint->grammar();

        $sql = $grammar->wrap($this->name) . ' ' . $grammar->type($this->logicalType, $this->options);

        // Only MySQL has UNSIGNED. Postgres rejects it and SQLite ignores it
        // silently, which is worse: the column then accepts negatives the
        // schema claims it cannot hold.
        if ($this->unsigned && $grammar->supportsUnsigned() && ! str_contains($sql, 'UNSIGNED')) {
            $sql .= ' UNSIGNED';
        }

        // MySQL's TIMESTAMP type already carries NULL to opt out of the
        // implicit NOT NULL DEFAULT CURRENT_TIMESTAMP; appending another
        // nullability keyword would be a syntax error.
        if (! str_ends_with($sql, ' NULL')) {
            $sql .= $this->isNullable ? ' NULL' : ' NOT NULL';
        } elseif (! $this->isNullable) {
            $sql = substr($sql, 0, -5) . ' NOT NULL';
        }

        if ($this->default !== '\0NONE\0') {
            $sql .= ' DEFAULT ' . $this->defaultValue($grammar);
        }

        return $sql;
    }

    private function defaultValue(Grammar $grammar): string
    {
        $value = $this->default;

        if ($value === null) {
            return 'NULL';
        }

        if (is_bool($value)) {
            // Postgres has a real boolean type and will not take 1 or 0 for it.
            return $grammar instanceof PostgresGrammar
                ? ($value ? 'TRUE' : 'FALSE')
                : (string) (int) $value;
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return "'" . str_replace("'", "''", (string) $value) . "'";
    }
}
