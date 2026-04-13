<?php

declare(strict_types=1);

namespace Libxa\Atlas\Relations;

/**
 * HasOne Relationship
 */
class HasOne extends HasMany
{
    /**
     * Return the first related model.
     */
    public function getResults()
    {
        return $this->query->first();
    }
}
