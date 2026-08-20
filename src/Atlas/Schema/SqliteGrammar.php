<?php

declare(strict_types=1);

namespace Libxa\Atlas\Schema;

final class SqliteGrammar extends Grammar
{
    public function wrap(string $identifier): string
    {
        // Backticks rather than double quotes. SQLite accepts both, but it
        // also treats "foo" as a string literal when no such column exists —
        // a misfeature that turns a typo into a query returning the column
        // name instead of an error. Backticks are never ambiguous.
        return '`' . str_replace('`', '``', $identifier) . '`';
    }

    public function increments(string $column): string
    {
        // SQLite only permits AUTOINCREMENT on INTEGER PRIMARY KEY — not
        // BIGINT, despite an INTEGER primary key already being 64-bit.
        return $this->wrap($column) . ' INTEGER PRIMARY KEY AUTOINCREMENT';
    }

    public function type(string $logical, array $options = []): string
    {
        return match ($logical) {
            'string'             => 'VARCHAR(' . ($options['length'] ?? 255) . ')',
            'uuid'               => 'VARCHAR(36)',
            'text', 'longText'   => 'TEXT',
            'integer'            => 'INTEGER',
            'unsignedInteger'    => 'INTEGER',
            'bigInteger'         => 'INTEGER',
            'unsignedBigInteger' => 'INTEGER',
            'boolean'            => 'INTEGER',
            'date'               => 'DATE',
            'dateTime'           => 'DATETIME',
            'timestamp'          => 'DATETIME',
            'json'               => 'TEXT',
            'binary'             => 'BLOB',
            'float'              => 'REAL',
            'decimal'            => 'NUMERIC(' . ($options['total'] ?? 8) . ',' . ($options['places'] ?? 2) . ')',
            // No ENUM: a CHECK constraint is how SQLite expresses the same
            // thing, and it actually rejects values outside the list.
            'enum'               => 'TEXT',
            default              => strtoupper($logical),
        };
    }
}
