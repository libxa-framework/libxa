<?php

declare(strict_types=1);

namespace Libxa\Config;

/**
 * Config Repository
 *
 * Loads PHP config files from config/ directory.
 * Supports dot-notation access: config('database.connections.default.host')
 */
class Config
{
    protected array $items = [];

    public function __construct(protected string $configPath) {}

    /**
     * Load all PHP files from the config directory.
     */
    public function load(): void
    {
        if (! is_dir($this->configPath)) return;

        foreach (glob($this->configPath . '/*.php') as $file) {
            $key = pathinfo($file, PATHINFO_FILENAME);
            $this->items[$key] = require $file;
        }
    }

    /**
     * Replace all config items with a pre-built array (used to hydrate from
     * the file written by `config:cache`, skipping the directory scan).
     */
    public function loadFromArray(array $items): void
    {
        $this->items = $items;
    }

    // ─────────────────────────────────────────────────────────────────
    //  Access
    // ─────────────────────────────────────────────────────────────────

    public function get(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $this->items)) {
            return $this->items[$key];
        }

        $parts  = explode('.', $key);
        $value  = $this->items;

        foreach ($parts as $segment) {
            if (! is_array($value) || ! array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    public function set(string $key, mixed $value): void
    {
        $parts = explode('.', $key);
        $ref   = &$this->items;

        foreach ($parts as $segment) {
            if (! isset($ref[$segment]) || ! is_array($ref[$segment])) {
                $ref[$segment] = [];
            }
            $ref = &$ref[$segment];
        }

        $ref = $value;
    }

    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    public function all(): array
    {
        return $this->items;
    }

    public function merge(string $key, array $values): void
    {
        $existing = (array) $this->get($key, []);
        $this->set($key, array_merge($values, $existing));
    }
}
