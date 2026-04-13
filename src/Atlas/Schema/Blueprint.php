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
        $sql = $this->toSql();
        $this->pdo->exec($sql);

        foreach ($this->indexes as $indexSql) {
            $this->pdo->exec($indexSql);
        }
    }
}

/**
 * Column definition with fluent modifiers.
 */
class ColumnDefinition
{
    protected bool    $isNullable = false;
    protected mixed   $default    = '\0NONE\0';
    protected bool    $isUnique   = false;
    protected bool    $unsigned   = false;

    public function __construct(
        protected string    $name,
        protected string    $type,
        protected Blueprint $blueprint,
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

    public function toSql(): string
    {
        $sql = "`{$this->name}` {$this->type}";

        if ($this->unsigned) $sql .= ' UNSIGNED';

        $sql .= $this->isNullable ? ' NULL' : ' NOT NULL';

        if ($this->default !== '\0NONE\0') {
            $val  = is_string($this->default) ? "'{$this->default}'" : (is_bool($this->default) ? ((int) $this->default) : $this->default);
            $sql .= " DEFAULT $val";
        }

        return $sql;
    }
}

/**
 * Foreign key definition.
 */
class ForeignKeyDefinition
{
    protected string $refTable  = '';
    protected string $refColumn = 'id';
    protected string $onDelete  = 'RESTRICT';
    protected string $onUpdate  = 'CASCADE';

    public function __construct(
        protected string    $column,
        protected Blueprint $blueprint,
    ) {}

    public function references(string $column): static { $this->refColumn = $column; return $this; }
    public function on(string $table): static          { $this->refTable  = $table;  return $this; }
    public function onDelete(string $action): static   { $this->onDelete  = strtoupper($action); return $this; }
    public function onUpdate(string $action): static   { $this->onUpdate  = strtoupper($action); return $this; }
    public function cascadeOnDelete(): static          { return $this->onDelete('CASCADE'); }
    public function nullOnDelete(): static             { return $this->onDelete('SET NULL'); }

    public function toSql(): string
    {
        return "FOREIGN KEY (`{$this->column}`) REFERENCES `{$this->refTable}` (`{$this->refColumn}`) ON DELETE {$this->onDelete} ON UPDATE {$this->onUpdate}";
    }
}
