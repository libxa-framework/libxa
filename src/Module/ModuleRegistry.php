<?php

declare(strict_types=1);

namespace Libxa\Module;

use Libxa\Foundation\Application;

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
