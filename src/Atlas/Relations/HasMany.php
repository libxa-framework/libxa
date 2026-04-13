<?php

declare(strict_types=1);

namespace Libxa\Atlas\Relations;

/**
 * HasMany Relationship
 */
class HasMany extends Relation
{
    protected string $foreignKey;
    protected string $localKey;

    public function __construct($query, $parent, string $foreignKey, string $localKey)
    {
        parent::__construct($query, $parent);
        $this->foreignKey = $foreignKey;
        $this->localKey = $localKey;

        $this->addConstraints();
    }

    protected function addConstraints(): void
    {
        $this->query->where($this->foreignKey, '=', $this->parent->getAttribute($this->localKey));
    }

    public function addEagerConstraints(array $models): void
    {
        $ids = array_map(fn($model) => $model->getAttribute($this->localKey), $models);
        $this->query->whereIn($this->foreignKey, $ids);
    }
}
