<?php

declare(strict_types=1);

namespace Libxa\Foundation;

use Libxa\Container\ServiceProvider;

/**
 * Base Module Service Provider
 * 
 * Every module in LibxaFrame implements this class.
 * It is the single entry point for the module: routes, services,
 * migrations, views, translations, config, and commands.
 */
abstract class ModuleServiceProvider extends ServiceProvider
{
    /**
     * Register pure bindings and merge configs.
     * Side effects (routing, DB) should be in boot().
     */
    public function register(): void
    {
        $this->registerConfigs();
    }

    /**
     * Bootstrap module services.
     */
    public function boot(): void
    {
        // To be implemented by child classes
    }

    /**
     * List of other module slugs this module depends on.
     */
    public function requires(): array
    {
        return [];
    }

    // ─────────────────────────────────────────────────────────────────
    //  Resource Loaders
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

    protected function loadTranslationsFrom(string $path, string $namespace): void
    {
        if ($this->app->has('translator')) {
            $this->app->make('translator')->addNamespace($namespace, $path);
        }
    }

    protected function loadCommandsFrom(string $path): void
    {
        if ($this->app->isCli() && is_dir($path)) {
            $this->app->make(\Libxa\Console\Application::class)->addFromDirectory($path);
        }
    }

    /**
     * Register event listeners.
     */
    protected function listen(array $events): void
    {
        if ($this->app->has('events')) {
            $dispatcher = $this->app->make('events');
            foreach ($events as $event => $listeners) {
                foreach ((array) $listeners as $listener) {
                    $dispatcher->listen($event, $listener);
                }
            }
        }
    }

    // ─────────────────────────────────────────────────────────────────
    //  Helpers
    // ─────────────────────────────────────────────────────────────────

    protected function registerConfigs(): void
    {
        $configDir = $this->modulePath('Config');
        
        if (is_dir($configDir)) {
            foreach (glob("$configDir/*.php") as $file) {
                $key = pathinfo($file, PATHINFO_FILENAME);
                $this->mergeConfigFrom($file, $key);
            }
        }
    }

    protected function modulePath(string $relative = ''): string
    {
        $reflector = new \ReflectionClass(static::class);
        $dir = dirname($reflector->getFileName());
        
        return $dir . ($relative ? DIRECTORY_SEPARATOR . ltrim($relative, '/\\') : '');
    }
}
