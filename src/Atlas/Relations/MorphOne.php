<?php

declare(strict_types=1);

namespace Libxa\Atlas\Relations;

/**
 * MorphOne Relationship (Polymorphic)
 */
class MorphOne extends MorphMany
{
    /**
     * Return the first related model.
     */
    public function getResults()
    {
        return $this->query->first();
    }
}
