<?php

declare(strict_types=1);

namespace Libxa\Module;

use Libxa\Foundation\Application;
use Libxa\Container\ServiceProvider;

/**
 * Base Module Class
 *
 * Every module in LibxaFrame implements this class.
 * It is the single entry point for the module: routes, services,
 * migrations, views, config, events.
 *
 * To create a module:
 *   php Libxa make:module Blog
 *
 * This creates:
 *   src/app/Modules/Blog/
 *   ├── Module.php        ← THIS file (auto-generated)
 *   ├── Controllers/
 *   ├── Models/
 *   ├── Services/
 *   ├── Events/
 *   ├── Listeners/
 *   ├── Jobs/
 *   ├── Migrations/
 *   ├── Views/
 *   ├── Config/
 *   └── routes.php
 *
 * The ModuleLoader discovers all Module.php files under src/app/Modules/
 * and calls register() + boot() automatically. Zero manual registration.
 */
abstract class Module extends ServiceProvider
{
    /** Module name (used as prefix for routes, views, config keys) */
    abstract public function moduleName(): string;

    /**
     * Register services, bindings, config.
     * Called before boot phase — keep it lightweight.
     */
    public function register(): void
    {
        // Auto-merge the module's config
        $configPath = $this->modulePath('Config');

        if (is_dir($configPath)) {
            foreach (glob("$configPath/*.php") as $file) {
                $key = pathinfo($file, PATHINFO_FILENAME);
                $this->mergeConfigFrom($file, $this->moduleName() . '.' . $key);
            }
        }
    }

    /**
     * Boot services. Called after all providers/modules have registered.
     * Used to load routes, views, migrations.
     */
    public function boot(): void
    {
        $this->loadRoutes();
        $this->loadViews();
        $this->loadMigrations2();
        $this->registerListeners();
    }

    // ─────────────────────────────────────────────────────────────────
    //  Auto-loading helpers
    // ─────────────────────────────────────────────────────────────────

    protected function loadRoutes(): void
    {
        $routesFile = $this->modulePath('routes.php');

        if (file_exists($routesFile)) {
            $this->loadRoutesFrom($routesFile);
        }

        // Also scan Controllers for attribute-based routes
        $controllersDir = $this->modulePath('Controllers');

        if (is_dir($controllersDir) && $this->app->has(\Libxa\Router\Router::class)) {
            $router    = $this->app->make(\Libxa\Router\Router::class);
            $namespace = $this->moduleNamespace() . '\\Controllers';
            $router->scanDirectory($controllersDir, $namespace);
        }
    }

    protected function loadViews(): void
    {
        $viewsPath = $this->modulePath('Views');

        if (is_dir($viewsPath)) {
            $this->loadViewsFrom($viewsPath, $this->moduleName());
        }
    }

    protected function loadMigrations2(): void
    {
        $migrationsPath = $this->modulePath('Migrations');

        if (is_dir($migrationsPath)) {
            $this->loadMigrationsFrom($migrationsPath);
        }
    }

    /**
     * Override to register event listeners.
     *
     * Example:
     *   protected function registerListeners(): void
     *   {
     *       $this->listen(UserRegistered::class, SendWelcomeEmail::class);
     *   }
     */
    protected function registerListeners(): void {}

    protected function listen(string $event, string|callable $listener): void
    {
        if ($this->app->has('events')) {
            $this->app->make('events')->listen($event, $listener);
        }
    }

    // ─────────────────────────────────────────────────────────────────
    //  Path helpers
    // ─────────────────────────────────────────────────────────────────

    /**
     * Get the absolute path to a file/directory inside this module.
     */
    public function modulePath(string $relative = ''): string
    {
        $reflector = new \ReflectionClass(static::class);
        $dir       = dirname($reflector->getFileName());

        return $dir . ($relative ? DIRECTORY_SEPARATOR . ltrim($relative, '/\\') : '');
    }

    /**
     * Get the PHP namespace for this module.
     */
    public function moduleNamespace(): string
    {
        $reflector = new \ReflectionClass(static::class);
        return $reflector->getNamespaceName();
    }

    // ─────────────────────────────────────────────────────────────────
    //  Module state
    // ─────────────────────────────────────────────────────────────────

    public function isEnabled(): bool
    {
        $key = 'modules.' . $this->moduleName() . '.enabled';
        return $this->app->config($key, true) !== false;
    }

    /**
     * Override to declare module metadata.
     */
    public function metadata(): array
    {
        return [
            'name'        => $this->moduleName(),
            'version'     => '1.0.0',
            'description' => '',
            'author'      => '',
        ];
    }
}
