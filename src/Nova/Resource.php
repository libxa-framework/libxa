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
