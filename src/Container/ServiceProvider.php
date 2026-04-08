<?php

declare(strict_types=1);

namespace Libxa\Container;

use Libxa\Foundation\Application;

/**
 * Base Service Provider
 *
 * All framework and user service providers extend this class.
 * Provides helper methods for binding registration.
 */
abstract class ServiceProvider
{
    public function __construct(protected Application $app) {}

    /**
     * Register bindings into the container.
     * Called before any other providers are booted.
     */
    public function register(): void {}

    /**
     * Bootstrap any application services.
     * Called after all providers have registered.
     */
    public function boot(): void {}

    // ─────────────────────────────────────────────────────────────────
    //  Helpers
    // ─────────────────────────────────────────────────────────────────

    protected function loadRoutesFrom(string $path, string $prefix = '', array $middleware = []): void
    {
        if (file_exists($path)) {
            $router = $this->app->make(\Libxa\Router\Router::class);

            if ($router === null) {
                return;
            }

            if ($prefix || ! empty($middleware)) {
                $router->group(['prefix' => $prefix, 'middleware' => $middleware], function() use ($path, $router) {
                    require $path;
                });
            } else {
                require $path;
            }
        }
    }

    protected function loadViewsFrom(string $path, string $namespace): void
    {
        if ($this->app->has('blade')) {
            $this->app->make('blade')->addNamespace($namespace, $path);
        }
    }

    protected function loadMigrationsFrom(string $path): void
    {
        if ($this->app->has('migrator')) {
            $this->app->make('migrator')->addPath($path);
        }
    }

    protected function loadTranslationsFrom(string $path, string $namespace): void
    {
        if ($this->app->has('translator')) {
            $this->app->make('translator')->addNamespace($namespace, $path);
        }
    }

    protected function mergeConfigFrom(string $path, string $key): void
    {
        if ($this->app->has('config')) {
            /** @var \Libxa\Config\Config $config */
            $config   = $this->app->make('config');
            $existing = $config->get($key, []);

            if (is_array($existing) && file_exists($path)) {
                $package = require $path;
                $config->set($key, array_merge($package, $existing));
            }
        }
    }

    protected function publishes(array $paths, string $group = 'default'): void
    {
        // Store publishable paths for `php Libxa vendor:publish`
        PublishableRegistry::register(static::class, $paths, $group);
    }
}

/**
 * Registry of publishable assets from packages.
 */
class PublishableRegistry
{
    protected static array $publishables = [];

    public static function register(string $provider, array $paths, string $group): void
    {
        static::$publishables[$provider][$group] = $paths;
    }

    public static function all(): array
    {
        return static::$publishables;
    }

    public static function forGroup(string $group): array
    {
        $result = [];

        foreach (static::$publishables as $provider => $groups) {
            if (isset($groups[$group])) {
                $result = array_merge($result, $groups[$group]);
            }
        }

        return $result;
    }
}
