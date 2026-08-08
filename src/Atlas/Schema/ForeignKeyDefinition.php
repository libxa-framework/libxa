<?php

declare(strict_types=1);

namespace Libxa\Atlas\Schema;

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
