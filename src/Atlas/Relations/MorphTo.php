<?php

declare(strict_types=1);

namespace Libxa\Atlas\Relations;

/**
 * MorphTo Relationship (Polymorphic)
 * e.g. Comment belongs to Post OR Video.
 */
class MorphTo extends Relation
{
    protected string $morphType;
    protected string $morphId;

    public function __construct($query, $parent, string $morphType, string $morphId)
    {
        parent::__construct($query, $parent);
        $this->morphType = $morphType;
        $this->morphId   = $morphId;
    }

    /**
     * Resolve the related model dynamically.
     */
    public function getResults()
    {
        $type = $this->parent->getAttribute($this->morphType);
        $id   = $this->parent->getAttribute($this->morphId);

        if (!$type || !$id) {
            return null;
        }

        // $type should be the fully qualified class name
        return $type::find($id);
    }

    public function addEagerConstraints(array $models): void
    {
        // Polymorphic eager loading is more complex as it targets multiple types.
        // Implementation here would typically group by type and execute individual whereIn queries.
    }
}
