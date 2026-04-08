<?php

declare(strict_types=1);

namespace Libxa\Nova;

use Libxa\Foundation\Application;

class ResourceManager
{
    /** @var array<string, Resource> */
    protected array $resources = [];

    /**
     * Create a new resource manager instance.
     */
    public function __construct(protected Application $app)
    {
    }

    /**
     * Discover and register all Nova resources.
     */
    public function discover(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }

        $files = glob("$path/*.php");

        foreach ($files as $file) {
            $class = "App\\Nova\\" . basename($file, '.php');
            
            if (class_exists($class) && is_subclass_of($class, Resource::class)) {
                $this->register(new $class());
            }
        }
    }

    /**
     * Register a resource manually.
     */
    public function register(Resource $resource): void
    {
        $this->resources[$resource::uriKey()] = $resource;
    }

    /**
     * Get all registered resources.
     */
    public function all(): array
    {
        return $this->resources;
    }

    /**
     * Get a resource by its URI key.
     */
    public function get(string $key): ?Resource
    {
        return $this->resources[$key] ?? null;
    }
}
