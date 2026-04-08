<?php

declare(strict_types=1);

namespace Libxa\Frontend\Contracts;

/**
 * Frontend Adapter Contract
 *
 * Implement this interface to add a new frontend framework to LibxaFrame.
 * Registered adapters are resolved by the Frontend facade.
 *
 * Built-in adapters: Blade, React, Vue3, Svelte, Alpine, Inertia
 */
interface FrontendAdapter
{
    /**
     * Adapter unique name (used for registration and config).
     * Example: 'react', 'vue', 'svelte'
     */
    public function name(): string;

    /**
     * Render a component/page.
     *
     * @param  string  $component  Component name or view path
     * @param  array   $props      Data passed to the component
     */
    public function render(string $component, array $props = []): string;

    /**
     * HTML tags to inject in the <head> for this adapter.
     * Example: Vite HMR scripts, preload links, etc.
     */
    public function headTags(): string;

    /**
     * HTML tags to inject before </body>.
     * Example: Hydration scripts, SSR state transfer.
     */
    public function bodyTags(): string;

    /**
     * Returns the Vite input entry file(s) for this adapter.
     * Example: ['resources/js/app.jsx']
     */
    public function viteEntries(): array;

    /**
     * Returns Vite plugin configuration snippets.
     * Used by `php Libxa frontend:install` to configure vite.config.js.
     */
    public function vitePluginConfig(): string;

    /**
     * Returns the npm packages required by this adapter.
     */
    public function npmDependencies(): array;
}
