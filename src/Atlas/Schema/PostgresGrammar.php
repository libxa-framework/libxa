<?php

declare(strict_types=1);

namespace Libxa\Atlas\Schema;

final class PostgresGrammar extends Grammar
{
    public function wrap(string $identifier): string
    {
        return '"' . str_replace('"', '""', $identifier) . '"';
    }

    public function increments(string $column): string
    {
        return $this->wrap($column) . ' BIGSERIAL PRIMARY KEY';
    }

    public function type(string $logical, array $options = []): string
    {
        return match ($logical) {
            'string'             => 'VARCHAR(' . ($options['length'] ?? 255) . ')',
            'uuid'               => 'UUID',
            'text', 'longText'   => 'TEXT',
            'integer'            => 'INTEGER',
            // Postgres has no unsigned integers at all. Widening keeps every
            // value representable, which matters most for a foreign key
            // pointing at a BIGSERIAL primary key.
            'unsignedInteger'    => 'BIGINT',
            'bigInteger'         => 'BIGINT',
            'unsignedBigInteger' => 'BIGINT',
            'boolean'            => 'BOOLEAN',
            'date'               => 'DATE',
            'dateTime'           => 'TIMESTAMP',
            'timestamp'          => 'TIMESTAMP',
            'json'               => 'JSONB',
            'binary'             => 'BYTEA',
            'float'              => 'DOUBLE PRECISION',
            'decimal'            => 'NUMERIC(' . ($options['total'] ?? 8) . ',' . ($options['places'] ?? 2) . ')',
            'enum'               => 'TEXT',
            default              => strtoupper($logical),
        };
    }
}
