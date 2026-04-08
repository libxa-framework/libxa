<?php

declare(strict_types=1);

namespace Libxa\Nova;

abstract class Resource
{
    /**
     * The model the resource corresponds to.
     */
    public static string $model = '';

    /**
     * The single value that should be used to represent the resource when being displayed.
     */
    public static string $title = 'id';

    /**
     * The columns that should be searched.
     */
    public static array $search = ['id'];

    /**
     * Get the fields displayed by the resource.
     */
    abstract public function fields(): array;

    /**
     * Get the display name of the resource.
     */
    public static function label(): string
    {
        return static::class;
    }

    /**
     * Get the URI key for the resource.
     */
    public static function uriKey(): string
    {
        return strtolower(basename(static::class));
    }
}
class Field
{
    public function __construct(
        public string $name,
        public string $attribute,
        public string $type = 'text',
        public bool $showOnIndex = true,
        public bool $showOnDetail = true,
        public bool $showOnForm = true,
        public bool $sortable = false,
    ) {}

    public static function make(string $name, string $attribute = null): static
    {
        return new static($name, $attribute ?? strtolower($name));
    }

    public function sortable(): static
    {
        $this->sortable = true;
        return $this;
    }

    public function hideFromIndex(): static
    {
        $this->showOnIndex = false;
        return $this;
    }
}
