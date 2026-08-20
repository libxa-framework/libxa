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

    protected Grammar $grammar;

    public function __construct(
        protected string $table,
        protected \PDO   $pdo,
        protected bool   $alter = false,
        ?Grammar $grammar = null,
    ) {
        $this->grammar = $grammar ?? Grammar::for($pdo);
    }

    public function grammar(): Grammar
    {
        return $this->grammar;
    }

    public function table(): string
    {
        return $this->table;
    }

    // ─────────────────────────────────────────────────────────────────
    //  Column types
    // ─────────────────────────────────────────────────────────────────

    public function id(string $column = 'id'): static
    {
        $this->incrementId = true;
        $this->columns[]   = $this->grammar->increments($column);
        return $this;
    }

    public function uuid(string $column = 'id'): static
    {
        $this->columns[] = $this->grammar->wrap($column) . ' ' . $this->grammar->type('uuid') . ' PRIMARY KEY';
        return $this;
    }

    public function string(string $column, int $length = 255): ColumnDefinition
    {
        return $this->addColumn($column, 'string', ['length' => $length]);
    }

    public function text(string $column): ColumnDefinition
    {
        return $this->addColumn($column, 'text');
    }

    public function longText(string $column): ColumnDefinition
    {
        return $this->addColumn($column, 'longText');
    }

    public function integer(string $column): ColumnDefinition
    {
        return $this->addColumn($column, 'integer');
    }

    public function unsignedInteger(string $column): ColumnDefinition
    {
        return $this->addColumn($column, 'unsignedInteger');
    }

    public function bigInteger(string $column): ColumnDefinition
    {
        return $this->addColumn($column, 'bigInteger');
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
        return $this->addColumn($column, 'unsignedBigInteger');
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
        return $this->addColumn($column, 'string', ['length' => 45]);
    }

    public function float(string $column, int $total = 8, int $places = 2): ColumnDefinition
    {
        return $this->addColumn($column, 'float', ['total' => $total, 'places' => $places]);
    }

    public function decimal(string $column, int $total = 8, int $places = 2): ColumnDefinition
    {
        return $this->addColumn($column, 'decimal', ['total' => $total, 'places' => $places]);
    }

    public function boolean(string $column): ColumnDefinition
    {
        return $this->addColumn($column, 'boolean');
    }

    public function date(string $column): ColumnDefinition
    {
        return $this->addColumn($column, 'date');
    }

    public function dateTime(string $column): ColumnDefinition
    {
        return $this->addColumn($column, 'dateTime');
    }

    public function timestamp(string $column): ColumnDefinition
    {
        return $this->addColumn($column, 'timestamp');
    }

    public function json(string $column): ColumnDefinition
    {
        return $this->addColumn($column, 'json');
    }

    public function enum(string $column, array $values): ColumnDefinition
    {
        return $this->addColumn($column, 'enum', ['values' => $values]);
    }

    public function binary(string $column): ColumnDefinition
    {
        return $this->addColumn($column, 'binary');
    }

    // ─────────────────────────────────────────────────────────────────
    //  Convenience
    // ─────────────────────────────────────────────────────────────────

    public function timestamps(): static
    {
        $this->addColumn('created_at', 'dateTime')->nullable();
        $this->addColumn('updated_at', 'dateTime')->nullable();
        return $this;
    }

    public function softDeletes(string $column = 'deleted_at'): static
    {
        $this->addColumn($column, 'dateTime')->nullable();
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
        return $this->addColumn($column, 'unsignedBigInteger');
    }

    public function rememberToken(): static
    {
        $this->addColumn('remember_token', 'string', ['length' => 100])->nullable();
        return $this;
    }

    // ─────────────────────────────────────────────────────────────────
    //  Indexes
    // ─────────────────────────────────────────────────────────────────

    public function index(string|array $columns, ?string $name = null): static
    {
        $cols = $this->grammar->wrapAll((array) $columns);
        $name = $name ?? 'idx_' . $this->table . '_' . implode('_', (array) $columns);
        $exists = $this->grammar->supportsIndexIfNotExists() ? 'IF NOT EXISTS ' : '';
        $this->indexes[] = 'CREATE INDEX ' . $exists . $this->grammar->wrap($name)
            . ' ON ' . $this->grammar->wrap($this->table) . " ($cols)";
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
        $cols = $this->grammar->wrapAll((array) $columns);

        $this->columns[] = "PRIMARY KEY ($cols)";

        return $this;
    }

    public function unique(string|array $columns, ?string $name = null): static
    {
        $cols = $this->grammar->wrapAll((array) $columns);
        $name = $name ?? 'uq_' . $this->table . '_' . implode('_', (array) $columns);
        $exists = $this->grammar->supportsIndexIfNotExists() ? 'IF NOT EXISTS ' : '';
        $this->indexes[] = 'CREATE UNIQUE INDEX ' . $exists . $this->grammar->wrap($name)
            . ' ON ' . $this->grammar->wrap($this->table) . " ($cols)";
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

    protected function addColumn(string $name, string $type, array $options = []): ColumnDefinition
    {
        $def             = new ColumnDefinition($name, $type, $this, $options);
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
            return $col instanceof ColumnDefinition ? $col->toSql($this->grammar) : $col;
        }, $this->columns);

        $foreigns = array_map(function ($f) {
            return $f->toSql();
        }, $this->foreigns);

        $all = array_merge($cols, $foreigns);

        $body = implode(',' . PHP_EOL . '  ', array_filter($all));

        return $verb . ' ' . $exists . $this->grammar->wrap($this->table)
            . ' (' . PHP_EOL . '  ' . $body . PHP_EOL . ')';
    }

    /**
     * Run a statement that is allowed to be a repeat, but not to be wrong.
     *
     * This used to catch \Throwable and ignore it, which made a genuinely
     * broken index indistinguishable from one that already existed: the
     * migration reported success and the index was simply absent. Only
     * "already exists" is tolerated now; anything else is a real failure and
     * says so.
     */
    protected function run(string $sql): void
    {
        try {
            $this->pdo->exec($sql);
        } catch (\Throwable $e) {
            if ($this->grammar->isAlreadyExists($e)) {
                return;
            }

            throw new \RuntimeException(
                sprintf('Schema statement failed: %s%s  %s', $e->getMessage(), PHP_EOL, $sql),
                0,
                $e,
            );
        }
    }

    public function build(): void
    {
        if ($this->alter) {
            foreach ($this->columns as $col) {
                $colSql = $col instanceof ColumnDefinition ? $col->toSql($this->grammar) : $col;

                $this->run(
                    'ALTER TABLE ' . $this->grammar->wrap($this->table) . " ADD COLUMN $colSql",
                );
            }
        } else {
            $this->pdo->exec($this->toSql());
        }

        foreach ($this->indexes as $indexSql) {
            $this->run($indexSql);
        }
    }
}
