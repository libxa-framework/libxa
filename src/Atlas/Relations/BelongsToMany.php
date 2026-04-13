<?php

declare(strict_types=1);

namespace Libxa\Atlas\Relations;

/**
 * BelongsToMany Relationship
 */
class BelongsToMany extends Relation
{
    protected string $pivotTable;
    protected string $foreignPivotKey;
    protected string $relatedPivotKey;

    public function __construct($query, $parent, string $pivotTable, string $foreignPivotKey, string $relatedPivotKey)
    {
        parent::__construct($query, $parent);
        $this->pivotTable      = $pivotTable;
        $this->foreignPivotKey = $foreignPivotKey;
        $this->relatedPivotKey = $relatedPivotKey;

        $this->addConstraints();
    }

    protected function addConstraints(): void
    {
        $relatedTable = $this->query->getTable();
        $primaryKey   = $this->query->getPrimaryKey();

        $this->query
            ->join($this->pivotTable, "{$this->pivotTable}.{$this->relatedPivotKey}", '=', "{$relatedTable}.{$primaryKey}")
            ->where("{$this->pivotTable}.{$this->foreignPivotKey}", '=', $this->parent->getKey());
    }

    public function addEagerConstraints(array $models): void
    {
        $ids = array_map(fn($model) => $model->getKey(), $models);
        $this->query->whereIn("{$this->pivotTable}.{$this->foreignPivotKey}", $ids);
    }

    /**
     * Filter results by a pivot table column.
     */
    public function wherePivot(string $column, mixed $operator, mixed $value = null): self
    {
        $this->query->where("{$this->pivotTable}.{$column}", $operator, $value);
        return $this;
    }
}
