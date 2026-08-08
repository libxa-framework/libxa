<?php

declare(strict_types=1);

namespace Libxa\Atlas\Schema;

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
