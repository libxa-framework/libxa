<?php

declare(strict_types=1);

namespace Libxa\Atlas\Schema;

/**
 * Schema Blueprint
 *
 * Fluent DSL for defining table structures in migrations.
 *
 * Usage:
 *   Schema::create('users', function (Blueprint $t) {
 *       $t->id();
 *       $t->string('name');
 *       $t->string('email')->unique();
 *       $t->timestamps();
 *   });
 */
class Blueprint
{
    protected array $columns    = [];
    protected array $indexes    = [];
    protected array $foreigns   = [];
    protected bool  $incrementId = false;

    public function __construct(
        protected string $table,
        protected \PDO   $pdo,
        protected bool   $alter = false,
    ) {}

    // ─────────────────────────────────────────────────────────────────
    //  Column types
    // ─────────────────────────────────────────────────────────────────

    public function id(string $column = 'id'): static
    {
        $this->incrementId = true;
        $this->columns[]   = "$column INTEGER PRIMARY KEY AUTOINCREMENT";
        return $this;
    }

    public function uuid(string $column = 'id'): static
    {
        $this->columns[] = "$column VARCHAR(36) PRIMARY KEY";
        return $this;
    }

    public function string(string $column, int $length = 255): ColumnDefinition
    {
        return $this->addColumn($column, "VARCHAR($length)");
    }

    public function text(string $column): ColumnDefinition
    {
        return $this->addColumn($column, 'TEXT');
    }

    public function longText(string $column): ColumnDefinition
    {
        return $this->addColumn($column, 'LONGTEXT');
    }

    public function integer(string $column): ColumnDefinition
    {
        return $this->addColumn($column, 'INTEGER');
    }

    public function unsignedInteger(string $column): ColumnDefinition
    {
        return $this->addColumn($column, 'INTEGER UNSIGNED');
    }

    public function bigInteger(string $column): ColumnDefinition
    {
        return $this->addColumn($column, 'BIGINT');
    }

    /**
     * The type a foreign key pointing at `id()` should be.
     *
     * `id()` produces an auto-incrementing big integer, so a column referencing
     * one has to be an unsigned big integer to match. `bigInteger()` is signed
     * and `unsignedInteger()` is too narrow, so without this there was no
     * correct type for the most common foreign key there is.
     */
    public function unsignedBigInteger(string $column): ColumnDefinition
    {
        return $this->addColumn($column, 'BIGINT UNSIGNED');
    }

    /**
     * An IP address.
     *
     * 45 characters, because that is the longest an IPv6 address gets once it
     * carries an embedded IPv4 one. VARCHAR(15) is the mistake this exists to
     * stop: it holds every IPv4 address and silently truncates every IPv6 one.
     */
    public function ipAddress(string $column): ColumnDefinition
    {
        return $this->addColumn($column, 'VARCHAR(45)');
    }

    public function float(string $column, int $total = 8, int $places = 2): ColumnDefinition
    {
        return $this->addColumn($column, "FLOAT($total,$places)");
    }

    public function decimal(string $column, int $total = 8, int $places = 2): ColumnDefinition
    {
        return $this->addColumn($column, "DECIMAL($total,$places)");
    }

    public function boolean(string $column): ColumnDefinition
    {
        return $this->addColumn($column, 'TINYINT(1)');
    }

    public function date(string $column): ColumnDefinition
    {
        return $this->addColumn($column, 'DATE');
    }

    public function dateTime(string $column): ColumnDefinition
    {
        return $this->addColumn($column, 'DATETIME');
    }

    public function timestamp(string $column): ColumnDefinition
    {
        return $this->addColumn($column, 'TIMESTAMP');
    }

    public function json(string $column): ColumnDefinition
    {
        return $this->addColumn($column, 'JSON');
    }

    public function enum(string $column, array $values): ColumnDefinition
    {
        $list = implode(',', array_map(fn($v) => "'$v'", $values));
        return $this->addColumn($column, "ENUM($list)");
    }

    public function binary(string $column): ColumnDefinition
    {
        return $this->addColumn($column, 'BLOB');
    }

    // ─────────────────────────────────────────────────────────────────
    //  Convenience
    // ─────────────────────────────────────────────────────────────────

    public function timestamps(): static
    {
        $this->addColumn('created_at', 'DATETIME')->nullable();
        $this->addColumn('updated_at', 'DATETIME')->nullable();
        return $this;
    }

    public function softDeletes(string $column = 'deleted_at'): static
    {
        $this->addColumn($column, 'DATETIME')->nullable();
        return $this;
    }

    public function morphs(string $name): static
    {
        $this->unsignedInteger("{$name}_id");
        $this->string("{$name}_type", 100);
        return $this;
    }

    public function foreignId(string $column): ColumnDefinition
    {
        return $this->addColumn($column, 'BIGINT UNSIGNED');
    }

    public function rememberToken(): static
    {
        $this->addColumn('remember_token', 'VARCHAR(100)')->nullable();
        return $this;
    }

    // ─────────────────────────────────────────────────────────────────
    //  Indexes
    // ─────────────────────────────────────────────────────────────────

    public function index(string|array $columns, ?string $name = null): static
    {
        $cols = implode(', ', (array) $columns);
        $name = $name ?? 'idx_' . $this->table . '_' . implode('_', (array) $columns);
        $this->indexes[] = "CREATE INDEX IF NOT EXISTS $name ON {$this->table} ($cols)";
        return $this;
    }

    /**
     * A primary key over one or more columns.
     *
     * Declared inside CREATE TABLE rather than added afterwards, because a
     * primary key is part of the table definition and SQLite cannot add one
     * with ALTER TABLE at all.
     *
     * A pivot table is the reason this exists: `primary(['role_id',
     * 'user_id'])` is what stops the same pairing being inserted twice, and
     * without it every pivot needs a surrogate id it has no use for.
     */
    public function primary(string|array $columns): static
    {
        $cols = implode(', ', array_map(static fn (string $c): string => "`{$c}`", (array) $columns));

        $this->columns[] = "PRIMARY KEY ($cols)";

        return $this;
    }

    public function unique(string|array $columns, ?string $name = null): static
    {
        $cols = implode(', ', (array) $columns);
        $name = $name ?? 'uq_' . $this->table . '_' . implode('_', (array) $columns);
        $this->indexes[] = "CREATE UNIQUE INDEX IF NOT EXISTS $name ON {$this->table} ($cols)";
        return $this;
    }

    public function foreign(string $column): ForeignKeyDefinition
    {
        $def = new ForeignKeyDefinition($column, $this);
        $this->foreigns[] = $def;
        return $def;
    }

    // ─────────────────────────────────────────────────────────────────
    //  Add column helper
    // ─────────────────────────────────────────────────────────────────

    protected function addColumn(string $name, string $type): ColumnDefinition
    {
        $def             = new ColumnDefinition($name, $type, $this);
        $this->columns[] = $def;
        return $def;
    }

    // ─────────────────────────────────────────────────────────────────
    //  SQL Generation & Execution
    // ─────────────────────────────────────────────────────────────────

    public function toSql(bool $create = true, bool $ifNotExists = true): string
    {
        $exists = $ifNotExists ? 'IF NOT EXISTS ' : '';
        $verb   = $create ? 'CREATE TABLE' : 'ALTER TABLE';

        $cols = array_map(function ($col) {
            return $col instanceof ColumnDefinition ? $col->toSql() : $col;
        }, $this->columns);

        $foreigns = array_map(function ($f) {
            return $f->toSql();
        }, $this->foreigns);

        $all = array_merge($cols, $foreigns);

        return "$verb {$exists}`{$this->table}` (\n  " . implode(",\n  ", array_filter($all)) . "\n)";
    }

    public function build(): void
    {
        if ($this->alter) {
            // ALTER TABLE: add each column individually
            foreach ($this->columns as $col) {
                $colSql = $col instanceof ColumnDefinition ? $col->toSql() : $col;
                try {
                    $this->pdo->exec("ALTER TABLE `{$this->table}` ADD COLUMN $colSql");
                } catch (\Throwable $e) {
                    // Column may already exist: silently skip
                }
            }
        } else {
            $sql = $this->toSql();
            $this->pdo->exec($sql);
        }

        foreach ($this->indexes as $indexSql) {
            try {
                $this->pdo->exec($indexSql);
            } catch (\Throwable $e) {
                // Index may already exist: silently skip
            }
        }
    }
}
