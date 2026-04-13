<?php

declare(strict_types=1);

namespace Libxa\Atlas\Relations;

/**
 * MorphMany Relationship (Polymorphic)
 */
class MorphMany extends Relation
{
    protected string $morphType;
    protected string $morphId;

    public function __construct($query, $parent, string $morphType, string $morphId)
    {
        parent::__construct($query, $parent);
        $this->morphType = $morphType;
        $this->morphId   = $morphId;

        $this->addConstraints();
    }

    protected function addConstraints(): void
    {
        $this->query
            ->where($this->morphType, '=', get_class($this->parent))
            ->where($this->morphId, '=', $this->parent->getKey());
    }

    public function addEagerConstraints(array $models): void
    {
        $ids = array_map(fn($model) => $model->getKey(), $models);
        
        $this->query
            ->where($this->morphType, '=', get_class($this->parent))
            ->whereIn($this->morphId, $ids);
    }
}
