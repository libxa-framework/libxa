<?php

declare(strict_types=1);

namespace Libxa\Atlas\Schema;

final class MySqlGrammar extends Grammar
{
    public function wrap(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }

    public function increments(string $column): string
    {
        return $this->wrap($column) . ' BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY';
    }

    public function supportsUnsigned(): bool
    {
        return true;
    }

    public function supportsIndexIfNotExists(): bool
    {
        // MySQL has never supported it. MariaDB does, but the two are not
        // told apart by the PDO driver name, so the safe answer covers both:
        // the builder checks for the index instead.
        return false;
    }

    public function type(string $logical, array $options = []): string
    {
        return match ($logical) {
            'string'             => 'VARCHAR(' . ($options['length'] ?? 255) . ')',
            'uuid'               => 'CHAR(36)',
            'text'               => 'TEXT',
            'longText'           => 'LONGTEXT',
            'integer'            => 'INT',
            'unsignedInteger'    => 'INT UNSIGNED',
            'bigInteger'         => 'BIGINT',
            'unsignedBigInteger' => 'BIGINT UNSIGNED',
            'boolean'            => 'TINYINT(1)',
            'date'               => 'DATE',
            'dateTime'           => 'DATETIME',
            'timestamp'          => 'TIMESTAMP NULL',
            'json'               => 'JSON',
            'binary'             => 'BLOB',
            'float'              => 'FLOAT(' . ($options['total'] ?? 8) . ',' . ($options['places'] ?? 2) . ')',
            'decimal'            => 'DECIMAL(' . ($options['total'] ?? 8) . ',' . ($options['places'] ?? 2) . ')',
            'enum'               => 'ENUM(' . implode(',', array_map(
                static fn ($v): string => "'" . str_replace("'", "''", (string) $v) . "'",
                $options['values'] ?? [],
            )) . ')',
            default              => strtoupper($logical),
        };
    }
}
