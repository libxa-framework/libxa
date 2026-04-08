<?php

declare(strict_types=1);

namespace Libxa\Module;

use Libxa\Foundation\Application;

/**
 * Module Loader
 *
 * Auto-discovers and boots modules found in src/app/Modules/.
 * All you need to do is drop a folder + Module.php — zero config.
 *
 * Discovery rules:
 *  1. Scan the modules directory for subdirectories
 *  2. In each subdir look for a Module.php file
 *  3. If the class extends Libxa\Module\Module, register it
 *  4. If the module is enabled (default: yes), boot it
 */
class ModuleLoader
{
    protected ModuleRegistry $registry;

    public function __construct(protected Application $app)
    {
        $this->registry = new ModuleRegistry();
        $this->app->instance(ModuleRegistry::class, $this->registry);
    }

    /**
     * Discover and load all modules from the given path.
     */
    public function discover(string $basePath): void
    {
        if (! is_dir($basePath)) {
            return;
        }

        $dirs = array_filter(glob("$basePath/*"), 'is_dir');

        foreach ($dirs as $dir) {
            $this->loadFrom($dir);
        }
    }

    /**
     * Load a module from a specific directory.
     */
    public function loadFrom(string $dir): void
    {
        $moduleFile = $dir . DIRECTORY_SEPARATOR . 'Module.php';

        // Discover the class name from the file
        $class = $this->resolveClass($moduleFile);

        if ($class === null) {
            return;
        }

        if (! class_exists($class)) {
            require_once $moduleFile;
        }

        if (! class_exists($class)) {
            return;
        }

        if (! is_subclass_of($class, Module::class)) {
            return;
        }

        /** @var Module $module */
        $module = new $class($this->app);

        // Skip disabled modules
        if (! $module->isEnabled()) {
            return;
        }

        // Register into DI container
        $module->register();

        // Track in global registry
        $this->registry->register($module);

        // Boot
        $module->boot();
    }

    /**
     * Resolve the fully-qualified class name from a PHP file.
     * Parses the namespace + class declaration without using autoloading.
     */
    protected function resolveClass(string $file): ?string
    {
        $src   = file_get_contents($file);
        $tokens = token_get_all($src);

        $namespace = '';
        $class     = '';
        $nsCapture = false;
        $clCapture = false;

        foreach ($tokens as $token) {
            if (! is_array($token)) {
                if ($nsCapture && $token === ';') $nsCapture = false;
                if ($clCapture) $clCapture = false;
                continue;
            }

            [$type, $value] = $token;

            if ($type === T_NAMESPACE) { $nsCapture = true; continue; }
            if ($type === T_CLASS)     { $clCapture = true; continue; }

            if ($nsCapture && in_array($type, [T_STRING, T_NS_SEPARATOR, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_NAME_RELATIVE])) {
                $namespace .= $value;
            }

            if ($clCapture && $type === T_STRING) {
                $class     = $value;
                $clCapture = false;
            }
        }

        if ($class === '') return null;

        return $namespace ? "$namespace\\$class" : $class;
    }
}

/**
 * Module Registry — holds all loaded module instances.
 */
class ModuleRegistry
{
    /** @var Module[] */
    protected array $modules = [];

    public function register(Module $module): void
    {
        $this->modules[$module->moduleName()] = $module;
    }

    public function all(): array
    {
        return $this->modules;
    }

    public function get(string $name): ?Module
    {
        return $this->modules[$name] ?? null;
    }

    public function has(string $name): bool
    {
        return isset($this->modules[$name]);
    }

    public function names(): array
    {
        return array_keys($this->modules);
    }

    public function count(): int
    {
        return count($this->modules);
    }
}
