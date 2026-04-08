<?php

declare(strict_types=1);

namespace Libxa\Lang;

use Libxa\Foundation\Application;

/**
 * LibxaLang Translator
 *
 * Handles string translation, localization, and placeholder replacement.
 * Supports dot-notation: __('messages.welcome')
 */
class Translator
{
    protected string $locale;
    protected string $fallbackLocale;
    protected array  $loaded      = [];
    protected array  $namespaces  = [];

    public function __construct(protected Application $app)
    {
        $this->locale         = $app->env('APP_LOCALE', 'en');
        $this->fallbackLocale = $app->env('APP_FALLBACK_LOCALE', 'en');
    }

    /**
     * Get the translation for the given key.
     */
    public function get(string $key, array $replace = [], ?string $locale = null): string
    {
        $locale = $locale ?: $this->locale;

        // 1. Break down key: "messages.welcome" -> ["messages", "welcome"]
        $line = $this->getLine($key, $locale);

        // 2. Try fallback if not found
        if ($line === $key && $locale !== $this->fallbackLocale) {
            $line = $this->getLine($key, $this->fallbackLocale);
        }

        // 3. Handle placeholders: "Hello :name" -> "Hello John"
        return $this->makeReplacements($line, $replace);
    }

    /**
     * Set the current locale.
     */
    public function setLocale(string $locale): void
    {
        $this->locale = $locale;
    }

    /**
     * Get the current locale.
     */
    public function getLocale(): string
    {
        return $this->locale;
    }

    /**
     * Register a namespace → translation path mapping.
     * Allows modules to add their own lang directories.
     * Keys are addressed as "namespace::group.key".
     */
    public function addNamespace(string $namespace, string $path): void
    {
        $this->namespaces[$namespace] = rtrim($path, DIRECTORY_SEPARATOR);
    }

    protected function getLine(string $key, string $locale): string
    {
        // Namespace support: "billing::messages.welcome"
        if (str_contains($key, '::')) {
            [$namespace, $rest] = explode('::', $key, 2);
            $segments = explode('.', $rest);
            $group    = array_shift($segments);
            $lines    = $this->loadNamespaced($locale, $namespace, $group);
        } else {
            $segments = explode('.', $key);
            $group    = array_shift($segments);
            $lines    = $this->load($locale, $group);
        }

        // Dot notation lookup
        $line = $lines;
        foreach ($segments as $segment) {
            if (isset($line[$segment])) {
                $line = $line[$segment];
            } else {
                return $key;
            }
        }

        return is_string($line) ? $line : $key;
    }

    protected function loadNamespaced(string $locale, string $namespace, string $group): array
    {
        $cacheKey = "ns:{$namespace}";

        if (isset($this->loaded[$locale][$cacheKey][$group])) {
            return $this->loaded[$locale][$cacheKey][$group];
        }

        if (! isset($this->namespaces[$namespace])) {
            return [];
        }

        $path = $this->namespaces[$namespace] . DIRECTORY_SEPARATOR . $locale . DIRECTORY_SEPARATOR . $group . '.php';

        $lines = file_exists($path) ? (include $path) : [];
        $this->loaded[$locale][$cacheKey][$group] = is_array($lines) ? $lines : [];

        return $this->loaded[$locale][$cacheKey][$group];
    }

    protected function load(string $locale, string $group): array
    {
        if (isset($this->loaded[$locale][$group])) {
            return $this->loaded[$locale][$group];
        }

        $path = $this->app->basePath("src/lang/{$locale}/{$group}.php");
        
        if (file_exists($path)) {
            $this->loaded[$locale][$group] = include $path;
        } else {
            // Check for JSON file
            $jsonPath = $this->app->basePath("src/lang/{$locale}.json");
            if (file_exists($jsonPath)) {
                $content = json_decode(file_get_contents($jsonPath), true) ?: [];
                $this->loaded[$locale] = array_merge($this->loaded[$locale] ?? [], $content);
                return $content[$group] ?? [];
            }
            $this->loaded[$locale][$group] = [];
        }

        return $this->loaded[$locale][$group];
    }

    protected function makeReplacements(string $line, array $replace): string
    {
        if (empty($replace)) {
            return $line;
        }

        foreach ($replace as $key => $value) {
            $line = str_replace(
                [':' . $key, ':' . strtoupper($key), ':' . ucfirst($key)],
                [$value, strtoupper((string)$value), ucfirst((string)$value)],
                $line
            );
        }

        return $line;
    }
}
