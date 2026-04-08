<?php

declare(strict_types=1);

namespace Libxa\Frontend;

use Libxa\Frontend\Contracts\FrontendAdapter;
use Libxa\Foundation\Application;

/**
 * Frontend Manager
 *
 * Registry + facade for all frontend adapters.
 *
 * Usage:
 *   Frontend::use('react');
 *   Frontend::render('UserProfile', ['user' => $user]);
 *
 * In config/frontend.php:
 *   'adapter' => 'react',
 */
class Frontend
{
    protected static array   $adapters = [];
    protected static ?string $active   = null;

    // ─────────────────────────────────────────────────────────────────
    //  Registration
    // ─────────────────────────────────────────────────────────────────

    public static function register(FrontendAdapter $adapter): void
    {
        static::$adapters[$adapter->name()] = $adapter;
    }

    public static function use(string $name): void
    {
        static::$active = $name;
    }

    public static function registerAll(): void
    {
        static::register(new Adapters\BladeAdapter());
        static::register(new Adapters\ReactAdapter());
        static::register(new Adapters\VueAdapter());
        static::register(new Adapters\SvelteAdapter());
        static::register(new Adapters\AlpineAdapter());
        static::register(new Adapters\InertiaAdapter());
    }

    // ─────────────────────────────────────────────────────────────────
    //  Resolution
    // ─────────────────────────────────────────────────────────────────

    public static function active(): FrontendAdapter
    {
        $name = static::$active
            ?? Application::getInstance()?->config('frontend.adapter', 'blade')
            ?? 'blade';

        return static::get($name);
    }

    public static function get(string $name): FrontendAdapter
    {
        if (! isset(static::$adapters[$name])) {
            throw new \InvalidArgumentException("Frontend adapter [$name] not registered. Available: " . implode(', ', array_keys(static::$adapters)));
        }

        return static::$adapters[$name];
    }

    // ─────────────────────────────────────────────────────────────────
    //  Proxy to active adapter
    // ─────────────────────────────────────────────────────────────────

    public static function render(string $component, array $props = []): string
    {
        return static::active()->render($component, $props);
    }

    public static function headTags(): string
    {
        return static::active()->headTags();
    }

    public static function bodyTags(): string
    {
        return static::active()->bodyTags();
    }

    public static function viteEntries(): array
    {
        return static::active()->viteEntries();
    }
}
