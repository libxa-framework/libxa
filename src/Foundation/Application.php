<?php

declare(strict_types=1);

namespace Libxa\Foundation;

use Libxa\Container\Container;
use Libxa\Container\ContextGraph;
use Libxa\Module\ModuleLoader;
use Libxa\Module\ModuleManifestManager;

/**
 * LibxaFrame Application
 *
 * The heart of the framework. Bootstraps the container,
 * detects runtime context, loads service providers and modules.
 */
class Application extends Container
{
    /** Framework version */
    public const VERSION = '0.0.1';

    /** Absolute path to the application root */
    protected string $basePath;

    /** Whether the application has been bootstrapped */
    protected bool $booted = false;

    /** Registered service providers */
    protected array $providers = [];

    /** Booted service providers */
    protected array $bootedProviders = [];

    /** Runtime context: http | cli | queue | test | ws */
    protected string $context = 'http';

    /** Environment variables cache */
    protected static array $env = [];

    public function __construct(string $basePath)
    {
        static::setInstance($this);

        $this->basePath = rtrim($basePath, DIRECTORY_SEPARATOR);
        $this->loadEnvironment();

        $this->detectContext();
        $this->bindPathsInContainer();
        $this->registerBaseBindings();
        $this->registerCoreProviders();
    }

    // ─────────────────────────────────────────────────────────────────
    //  Bootstrap
    // ─────────────────────────────────────────────────────────────────

    /**
     * Boot all registered service providers.
     */
    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        $this->loadPackages();
        $this->loadModules();

        foreach ($this->providers as $provider) {
            if (! in_array($provider, $this->bootedProviders)) {
                $this->bootProvider($provider);
            }
        }

        $this->booted = true;
    }

    /**
     * Load auto-discovered packages from vendor.
     */
    protected function loadPackages(): void
    {
        $manifest = (new ModuleManifestManager($this, 'packages'))->load();

        foreach ($manifest as $package) {
            // Support plural "providers" array (recommended)
            if (isset($package['providers']) && is_array($package['providers'])) {
                foreach ($package['providers'] as $provider) {
                    if (class_exists($provider)) {
                        $this->register($provider);
                    }
                }
            }

            // Fallback to singular "provider"
            if (isset($package['provider']) && class_exists($package['provider'])) {
                $this->register($package['provider']);
            }
        }
    }

    /**
     * Register + boot a service provider.
     */
    public function register(string|object $provider): static
    {
        if (is_string($provider)) {
            $provider = new $provider($this);
        }

        $provider->register();
        $this->providers[] = $provider;

        if ($this->booted) {
            $this->bootProvider($provider);
        }

        return $this;
    }

    protected function bootProvider(object $provider): void
    {
        if (method_exists($provider, 'boot')) {
            $this->call([$provider, 'boot']);
        }
        $this->bootedProviders[] = $provider;
    }

    // ─────────────────────────────────────────────────────────────────
    //  Context Detection
    // ─────────────────────────────────────────────────────────────────

    protected function detectContext(): void
    {
        if (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg') {
            $this->context = 'cli';
        } elseif (isset($_SERVER['HTTP_X_Libxa_WS'])) {
            $this->context = 'ws';
        } elseif (getenv('Libxa_RUNTIME') === 'desktop') {
            $this->context = 'desktop';
        } elseif (getenv('Libxa_RUNTIME') === 'queue') {
            $this->context = 'queue';
        } elseif (getenv('Libxa_RUNTIME') === 'test') {
            $this->context = 'test';
        } else {
            $this->context = 'http';
        }

        $this->instance('context', $this->context);
    }

    public function context(): string
    {
        return $this->context;
    }

    public function isHttp(): bool    { return $this->context === 'http'; }
    public function isCli(): bool     { return $this->context === 'cli'; }
    public function isQueue(): bool   { return $this->context === 'queue'; }
    public function isWs(): bool      { return $this->context === 'ws'; }
    public function isDesktop(): bool { return $this->context === 'desktop'; }
    public function isTesting(): bool { return $this->context === 'test' || getenv('APP_ENV') === 'testing'; }

    // ─────────────────────────────────────────────────────────────────
    //  Paths
    // ─────────────────────────────────────────────────────────────────

    public function basePath(string $path = ''): string
    {
        return $this->basePath . ($path ? DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR) : '');
    }

    public function appPath(string $path = ''): string
    {
        return $this->basePath('src/app' . ($path ? '/' . ltrim($path, '/') : ''));
    }

    public function configPath(string $path = ''): string
    {
        return $this->basePath('src/config' . ($path ? '/' . ltrim($path, '/') : ''));
    }

    public function storagePath(string $path = ''): string
    {
        return $this->basePath('src/storage' . ($path ? '/' . ltrim($path, '/') : ''));
    }

    public function resourcePath(string $path = ''): string
    {
        return $this->basePath('src/resources' . ($path ? '/' . ltrim($path, '/') : ''));
    }

    public function publicPath(string $path = ''): string
    {
        return $this->basePath('src/public' . ($path ? '/' . ltrim($path, '/') : ''));
    }

    public function databasePath(string $path = ''): string
    {
        return $this->basePath('database' . ($path ? '/' . ltrim($path, '/') : ''));
    }

    public function viewPath(string $path = ''): string
    {
        return $this->resourcePath('views' . ($path ? '/' . ltrim($path, '/') : ''));
    }

    public function modulesPath(string $path = ''): string
    {
        return $this->appPath('Modules' . ($path ? '/' . ltrim($path, '/') : ''));
    }

    protected function bindPathsInContainer(): void
    {
        $this->instance('path.base',     $this->basePath());
        $this->instance('path.app',      $this->appPath());
        $this->instance('path.config',   $this->configPath());
        $this->instance('path.storage',  $this->storagePath());
        $this->instance('path.resource', $this->resourcePath());
        $this->instance('path.public',   $this->publicPath());
        $this->instance('path.database', $this->databasePath());
        $this->instance('path.modules',  $this->modulesPath());
    }

    // ─────────────────────────────────────────────────────────────────
    //  Environment
    // ─────────────────────────────────────────────────────────────────

    protected function loadEnvironment(): void
    {
        $envFile = $this->basePath('.env');

        if (! file_exists($envFile)) {
            return;
        }

        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            if (str_starts_with(trim($line), '#')) {
                continue;
            }

            if (str_contains($line, '=')) {
                [$key, $value] = explode('=', $line, 2);
                $key   = trim($key);
                $value = trim($value, " \t\n\r\0\x0B\"'");

                static::$env[$key] = $value;
                $_ENV[$key]        = $value;

                if (! getenv($key)) {
                    putenv("$key=$value");
                }
            }
        }
    }

    public static function env(string $key, mixed $default = null): mixed
    {
        return static::$env[$key] ?? getenv($key) ?: $_ENV[$key] ?? $default;
    }

    // ─────────────────────────────────────────────────────────────────
    //  Dependency Injection Overrides
    // ─────────────────────────────────────────────────────────────────

    /**
     * Resolve dependencies with FormRequest support.
     */
    protected function resolveDependencies(array $parameters, array $overrides = []): array
    {
        $dependencies = [];

        foreach ($parameters as $param) {
            $name = $param->getName();

            if (isset($overrides[$name])) {
                $dependencies[] = $overrides[$name];
                continue;
            }

            $type = $param->getType();

            if ($type instanceof \ReflectionNamedType && ! $type->isBuiltin()) {
                $className = $type->getName();

                // ⚡ FormRequest Auto-Injection & Validation
                if (is_subclass_of($className, \Libxa\Http\FormRequest::class)) {
                    $formRequest = $className::capture();
                    $formRequest->validateResolved();
                    $dependencies[] = $formRequest;
                    continue;
                }

                try {
                    $dependencies[] = $this->make($className);
                } catch (\Throwable $e) {
                    if ($param->isDefaultValueAvailable()) {
                        $dependencies[] = $param->getDefaultValue();
                    } elseif ($param->allowsNull()) {
                        $dependencies[] = null;
                    } else {
                        throw $e;
                    }
                }
            } elseif ($param->isDefaultValueAvailable()) {
                $dependencies[] = $param->getDefaultValue();
            } else {
                $dependencies[] = null;
            }
        }

        return $dependencies;
    }

    // ─────────────────────────────────────────────────────────────────
    //  Bindings
    // ─────────────────────────────────────────────────────────────────

    protected function registerBaseBindings(): void
    {
        $this->instance('app', $this);
        $this->instance(Application::class, $this);
        $this->instance(Container::class, $this);
        $this->instance(ContextGraph::class, new ContextGraph($this->context));
    }

    protected function registerCoreProviders(): void
    {
        // Core providers auto-registered
        $coreProviders = [
            \Libxa\Providers\ConfigServiceProvider::class,
            \Libxa\Providers\LangServiceProvider::class,
            \Libxa\Providers\RouterServiceProvider::class,
            \Libxa\Providers\DatabaseServiceProvider::class,
            \Libxa\Providers\BladeServiceProvider::class,
            \Libxa\Providers\EventServiceProvider::class,
            \Libxa\Providers\SessionServiceProvider::class,
            \Libxa\Providers\AuthServiceProvider::class,
            \Libxa\Providers\StorageServiceProvider::class,
            \Libxa\Providers\CacheServiceProvider::class,
            \Libxa\Providers\QueueServiceProvider::class,
            \Libxa\Providers\MailServiceProvider::class,
            \Libxa\Providers\BroadcastServiceProvider::class,
            \Libxa\Providers\NovaServiceProvider::class,
            \Libxa\Providers\PulseServiceProvider::class,
        ];

        foreach ($coreProviders as $provider) {
            if (class_exists($provider)) {
                $this->register($provider);
            }
        }
    }

    // ─────────────────────────────────────────────────────────────────
    //  Module Loading
    // ─────────────────────────────────────────────────────────────────

    protected function loadModules(): void
    {
        $modulesPath = $this->modulesPath();

        if (! is_dir($modulesPath)) {
            return;
        }

        $manager = new ModuleManifestManager($this, 'modules');
        $manifest = $manager->load();

        if ($manager->needsRebuild($modulesPath)) {
            $manifest = $manager->discover($modulesPath);
        }

        foreach ($manifest as $module) {
            if (isset($module['provider']) && class_exists($module['provider'])) {
                $this->register($module['provider']);
            }
        }
    }

    // ─────────────────────────────────────────────────────────────────
    //  Config
    // ─────────────────────────────────────────────────────────────────

    public function config(string $key, mixed $default = null): mixed
    {
        /** @var \Libxa\Config\Config|null $config */
        $config = $this->has('config') ? $this->make('config') : null;

        return $config?->get($key, $default) ?? $default;
    }

    // ─────────────────────────────────────────────────────────────────
    //  Singleton instance
    // ─────────────────────────────────────────────────────────────────

    // Instance management is inherited from Container

    public function version(): string
    {
        return self::VERSION;
    }
}
