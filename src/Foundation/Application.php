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

    /** Whether boot() is currently in progress (re-entrancy guard) */
    protected bool $booting = false;

    /** Registered service providers, keyed by class name */
    protected array $providers = [];

    /** Booted service provider class names => true */
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

        // Re-entrancy guard: a provider whose boot() reaches back into
        // app()->boot() (directly or through a helper) used to recurse until
        // the stack blew, because $booted was only set at the very end.
        if ($this->booting) {
            return;
        }

        $this->booting = true;

        try {
            $this->loadPackages();
            $this->loadModules();

            // Iterate over a snapshot: bootProvider() may register further
            // providers, and mutating $this->providers mid-foreach is undefined.
            // Loop until the set stabilises so late arrivals still get booted.
            do {
                $pending = array_diff_key($this->providers, $this->bootedProviders);

                foreach ($pending as $provider) {
                    $this->bootProvider($provider);
                }
            } while ($pending !== []);
        } finally {
            $this->booting = false;
        }

        $this->booted = true;
    }

    /**
     * Load auto-discovered packages from vendor and packages/ directory.
     */
    protected function loadPackages(): void
    {
        // Load packages from packages/ directory
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

        // Load Composer-installed packages from vendor/ directory
        $vendorDir = $this->basePath() . '/vendor';
        if (is_dir($vendorDir)) {
            $installedJson = $vendorDir . '/composer/installed.json';
            if (file_exists($installedJson)) {
                $installed = json_decode(file_get_contents($installedJson), true);
                $packages = $installed['packages'] ?? $installed;
                
                foreach ($packages as $package) {
                    $name = $package['name'] ?? '';
                    $extra = $package['extra'] ?? [];
                    
                    // Only load libxa packages
                    if (str_starts_with($name, 'libxa/') || str_starts_with($name, 'libxaframe/')) {
                        // Check for laravel-style provider discovery
                        if (isset($extra['laravel']['providers'])) {
                            foreach ($extra['laravel']['providers'] as $provider) {
                                if (class_exists($provider)) {
                                    $this->register($provider);
                                }
                            }
                        }
                        // Check for libxa-style provider discovery
                        if (isset($extra['Libxa']['providers'])) {
                            foreach ($extra['Libxa']['providers'] as $provider) {
                                if (class_exists($provider)) {
                                    $this->register($provider);
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * Register + boot a service provider.
     */
    public function register(string|object $provider, bool $force = false): static
    {
        $class = is_string($provider) ? $provider : $provider::class;

        // A provider reachable from several discovery paths (core list,
        // config/app.php, package manifest, module manifest) used to be
        // registered once per path: duplicating every route, event listener
        // and console command it declares.
        if (! $force && isset($this->providers[$class])) {
            return $this;
        }

        if (is_string($provider)) {
            $provider = new $provider($this);
        }

        $provider->register();
        $this->providers[$class] = $provider;

        if ($this->booted) {
            $this->bootProvider($provider);
        }

        return $this;
    }

    /**
     * Whether a provider class has already been registered.
     */
    public function providerIsRegistered(string $class): bool
    {
        return isset($this->providers[$class]);
    }

    protected function bootProvider(object $provider): void
    {
        $class = $provider::class;

        if (isset($this->bootedProviders[$class])) {
            return;
        }

        // Mark as booted *before* calling boot(): a provider whose boot()
        // triggers register() of another provider (which re-enters this loop)
        // would otherwise be booted twice.
        $this->bootedProviders[$class] = true;

        if (method_exists($provider, 'boot')) {
            $this->call([$provider, 'boot']);
        }
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

    /**
     * Join path segments with the platform separator.
     *
     * The sub-path helpers used to hard-code forward slashes ('src/app')
     * while basePath() joined with DIRECTORY_SEPARATOR, producing mixed
     * separators on Windows: "C:\app\src/app". PHP's file functions tolerate
     * that, but string comparisons, cache keys derived from paths, and
     * anything shown in an error message do not.
     */
    protected function joinPath(string ...$segments): string
    {
        $parts = [];

        foreach ($segments as $segment) {
            $segment = trim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $segment), DIRECTORY_SEPARATOR);

            if ($segment !== '') {
                $parts[] = $segment;
            }
        }

        return implode(DIRECTORY_SEPARATOR, $parts);
    }

    public function basePath(string $path = ''): string
    {
        return $path === ''
            ? $this->basePath
            : $this->basePath . DIRECTORY_SEPARATOR . $this->joinPath($path);
    }

    public function appPath(string $path = ''): string
    {
        return $this->basePath($this->joinPath('src', 'app', $path));
    }

    public function configPath(string $path = ''): string
    {
        return $this->basePath($this->joinPath('src', 'config', $path));
    }

    public function storagePath(string $path = ''): string
    {
        return $this->basePath($this->joinPath('src', 'storage', $path));
    }

    public function resourcePath(string $path = ''): string
    {
        return $this->basePath($this->joinPath('src', 'resources', $path));
    }

    public function publicPath(string $path = ''): string
    {
        return $this->basePath($this->joinPath('src', 'public', $path));
    }

    public function databasePath(string $path = ''): string
    {
        return $this->basePath($this->joinPath('database', $path));
    }

    public function viewPath(string $path = ''): string
    {
        return $this->resourcePath($this->joinPath('views', $path));
    }

    public function modulesPath(string $path = ''): string
    {
        return $this->appPath($this->joinPath('Modules', $path));
    }

    public function langPath(string $path = ''): string
    {
        return $this->basePath($this->joinPath('src', 'lang', $path));
    }

    public function routesPath(string $path = ''): string
    {
        return $this->basePath($this->joinPath('src', 'routes', $path));
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

        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            // Allow the "export KEY=value" form used by shell-sourced .env files.
            if (str_starts_with($line, 'export ')) {
                $line = ltrim(substr($line, 7));
            }

            if (! str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);

            $key = trim($key);
            if ($key === '' || ! preg_match('/^[A-Za-z_][A-Za-z0-9_.]*$/', $key)) {
                continue;
            }

            static::$env[$key] = $value = static::parseEnvValue($value);
            $_ENV[$key]        = $value;

            // getenv() returns "0"/"" as legitimate values, so a truthiness
            // check here would clobber a real environment variable of "0".
            if (getenv($key) === false) {
                putenv("$key=$value");
            }
        }
    }

    /**
     * Parse the right-hand side of a .env assignment.
     *
     * Handles quoted values (which may legitimately contain '#' or spaces),
     * strips trailing inline comments from unquoted values, and expands the
     * standard escape sequences inside double quotes. The previous version
     * ran trim($value, " \"'") over the raw string, which mangled values such
     * as   APP_NAME="My App"   # comment   and any password ending in a quote.
     */
    protected static function parseEnvValue(string $value): string
    {
        $value = ltrim($value);

        if ($value === '') {
            return '';
        }

        $quote = $value[0];

        if ($quote === '"' || $quote === "'") {
            // Find the closing quote, skipping escaped ones in double quotes.
            $len = strlen($value);
            for ($i = 1; $i < $len; $i++) {
                if ($quote === '"' && $value[$i] === '\\') {
                    $i++;
                    continue;
                }
                if ($value[$i] === $quote) {
                    $inner = substr($value, 1, $i - 1);
                    return $quote === '"' ? stripcslashes($inner) : $inner;
                }
            }

            // Unterminated quote: fall back to the raw remainder.
            return substr($value, 1);
        }

        // Unquoted: an unescaped '#' starts an inline comment.
        if (($hash = strpos($value, ' #')) !== false) {
            $value = substr($value, 0, $hash);
        }

        return rtrim($value);
    }

    /**
     * Read an environment variable.
     *
     * The previous implementation mixed ?? and ?: in one expression:
     *   static::$env[$key] ?? getenv($key) ?: $_ENV[$key] ?? $default
     * which PHP groups as (a ?? b) ?: (c ?? d). Any *falsy* value: "0",
     * "", "false": therefore fell through to the default, so APP_DEBUG=0
     * behaved exactly like an unset APP_DEBUG. Each source is now checked
     * for presence rather than truthiness, and the common literals are
     * cast to real PHP types the way every mainstream framework does.
     */
    public static function env(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, static::$env)) {
            return static::castEnv(static::$env[$key]);
        }

        $value = getenv($key);
        if ($value !== false) {
            return static::castEnv($value);
        }

        if (array_key_exists($key, $_ENV)) {
            return static::castEnv($_ENV[$key]);
        }

        if (array_key_exists($key, $_SERVER)) {
            return static::castEnv($_SERVER[$key]);
        }

        return $default;
    }

    /**
     * Read an environment variable as a strict boolean.
     *
     * Accepts every spelling people actually put in a .env file
     * ("true"/"1"/"on"/"yes" and their negatives) instead of the
     * === 'true' string comparisons that used to be scattered around
     * the framework and silently treated FEATURE=1 as disabled.
     */
    public static function envBool(string $key, bool $default = false): bool
    {
        $value = static::env($key);

        if ($value === null || $value === '') {
            return $default;
        }

        if (is_bool($value)) {
            return $value;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $default;
    }

    /**
     * Turn the conventional .env literals into PHP values.
     */
    protected static function castEnv(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        return match (strtolower($value)) {
            'true', '(true)'   => true,
            'false', '(false)' => false,
            'null', '(null)'   => null,
            'empty', '(empty)' => '',
            default            => $value,
        };
    }

    // ─────────────────────────────────────────────────────────────────
    //  Dependency Injection Overrides
    // ─────────────────────────────────────────────────────────────────

    /**
     * Resolve a class-typed parameter, adding FormRequest auto-injection
     * (capture + validate) on top of the container's normal resolution.
     *
     * This used to be a full copy of Container::resolveDependencies(), which
     * meant every hardening fix had to be applied twice and the two copies
     * had already drifted apart. Only the FormRequest special case is
     * overridden now; everything else is inherited.
     */
    protected function resolveClassDependency(\ReflectionParameter $param, string $className): mixed
    {
        if (is_subclass_of($className, \Libxa\Http\FormRequest::class)) {
            $formRequest = $className::capture();
            $formRequest->validateResolved();

            return $formRequest;
        }

        return parent::resolveClassDependency($param, $className);
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
            \Libxa\Providers\AiServiceProvider::class,
            \Libxa\Providers\SecurityServiceProvider::class,
            \Libxa\Providers\NovaServiceProvider::class,
            \Libxa\Providers\PulseServiceProvider::class,
        ];

        foreach ($coreProviders as $provider) {
            if (class_exists($provider)) {
                $this->register($provider);
            }
        }

        // Load providers from config file
        if (file_exists($this->configPath('app.php'))) {
            $appConfig = require $this->configPath('app.php');
            if (isset($appConfig['providers']) && is_array($appConfig['providers'])) {
                foreach ($appConfig['providers'] as $provider) {
                    if (class_exists($provider)) {
                        $this->register($provider);
                    }
                }
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
