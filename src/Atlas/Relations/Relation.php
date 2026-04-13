<?php

declare(strict_types=1);

namespace Libxa\Atlas\Relations;

use Libxa\Atlas\QueryBuilder;
use Libxa\Atlas\Model;

/**
 * Base Relation class.
 * Wraps a QueryBuilder instance to provide fluent chaining on models.
 */
abstract class Relation
{
    protected QueryBuilder $query;
    protected Model $parent;
    protected string $relatedModel;

    public function __construct(QueryBuilder $query, Model $parent)
    {
        $this->query = $query;
        $this->parent = $parent;
        $this->relatedModel = $query->getModel();
    }

    /**
     * Get the underlying query builder.
     */
    public function getQuery(): QueryBuilder
    {
        return $this->query;
    }

    /**
     * Get the parent model instance.
     */
    public function getParent(): Model
    {
        return $this->parent;
    }

    /**
     * Placeholder for eager loading logic.
     */
    abstract public function addEagerConstraints(array $models): void;

    /**
     * Pass methodology through to the query builder.
     */
    public function __call(string $method, array $parameters): mixed
    {
        $result = $this->query->$method(...$parameters);

        if ($result instanceof QueryBuilder) {
            return $this;
        }

        return $result;
    }

    /**
     * Execute the query and get results.
     */
    public function get(): array
    {
        return $this->query->get();
    }

    /**
     * Execute the query and get the first result.
     */
    public function first(): ?Model
    {
        return $this->query->first();
    }
}
