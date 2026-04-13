<?php

declare(strict_types=1);

namespace Libxa\Atlas\Relations;

/**
 * BelongsTo Relationship
 */
class BelongsTo extends Relation
{
    protected string $foreignKey;
    protected string $ownerKey;

    public function __construct($query, $parent, string $foreignKey, string $ownerKey)
    {
        parent::__construct($query, $parent);
        $this->foreignKey = $foreignKey;
        $this->ownerKey = $ownerKey;

        $this->addConstraints();
    }

    protected function addConstraints(): void
    {
        $this->query->where($this->ownerKey, '=', $this->parent->getAttribute($this->foreignKey));
    }

    public function addEagerConstraints(array $models): void
    {
        $ids = array_map(fn($model) => $model->getAttribute($this->foreignKey), $models);
        $this->query->whereIn($this->ownerKey, array_unique(array_filter($ids)));
    }
}
