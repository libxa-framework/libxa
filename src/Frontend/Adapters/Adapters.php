<?php

declare(strict_types=1);

namespace Libxa\Frontend\Adapters;

use Libxa\Frontend\Contracts\FrontendAdapter;
use Libxa\Foundation\Application;

// ─────────────────────────────────────────────────────────────────────
//  Blade Adapter (default)
// ─────────────────────────────────────────────────────────────────────

class BladeAdapter implements FrontendAdapter
{
    public function name(): string { return 'blade'; }

    public function render(string $component, array $props = []): string
    {
        return Application::getInstance()->make('blade')->render($component, $props);
    }

    public function headTags(): string
    {
        return \Libxa\Frontend\ViteManifest::tags(['resources/css/app.css']);
    }

    public function bodyTags(): string
    {
        return \Libxa\Frontend\ViteManifest::tags(['resources/js/app.js']);
    }

    public function viteEntries(): array { return ['resources/js/app.js', 'resources/css/app.css']; }

    public function vitePluginConfig(): string { return '// Blade: no Vite plugin needed'; }

    public function npmDependencies(): array { return []; }
}

// ─────────────────────────────────────────────────────────────────────
//  React Adapter
// ─────────────────────────────────────────────────────────────────────

class ReactAdapter implements FrontendAdapter
{
    public function name(): string { return 'react'; }

    public function render(string $component, array $props = []): string
    {
        $propsJson = htmlspecialchars(json_encode($props, JSON_UNESCAPED_UNICODE), ENT_QUOTES);
        $id        = 'Libxa-react-' . md5($component . serialize($props));

        return <<<HTML
        <div id="{$id}" data-Libxa-react="{$component}" data-props="{$propsJson}"></div>
        HTML;
    }

    public function headTags(): string
    {
        return \Libxa\Frontend\ViteManifest::tags(['resources/js/app.jsx', 'resources/css/app.css']);
    }

    public function bodyTags(): string { return ''; }

    public function viteEntries(): array { return ['resources/js/app.jsx']; }

    public function vitePluginConfig(): string
    {
        return "import react from '@vitejs/plugin-react';\n// plugins: [react()]";
    }

    public function npmDependencies(): array
    {
        return ['react', 'react-dom', '@vitejs/plugin-react'];
    }
}

// ─────────────────────────────────────────────────────────────────────
//  Vue 3 Adapter
// ─────────────────────────────────────────────────────────────────────

class VueAdapter implements FrontendAdapter
{
    public function name(): string { return 'vue'; }

    public function render(string $component, array $props = []): string
    {
        $propsJson = htmlspecialchars(json_encode($props, JSON_UNESCAPED_UNICODE), ENT_QUOTES);
        $id        = 'Libxa-vue-' . md5($component . serialize($props));

        return <<<HTML
        <div id="{$id}" data-Libxa-vue="{$component}" data-props="{$propsJson}"></div>
        HTML;
    }

    public function headTags(): string
    {
        return \Libxa\Frontend\ViteManifest::tags(['resources/js/app.js', 'resources/css/app.css']);
    }

    public function bodyTags(): string { return ''; }

    public function viteEntries(): array { return ['resources/js/app.js']; }

    public function vitePluginConfig(): string
    {
        return "import vue from '@vitejs/plugin-vue';\n// plugins: [vue()]";
    }

    public function npmDependencies(): array
    {
        return ['vue', '@vitejs/plugin-vue'];
    }
}

// ─────────────────────────────────────────────────────────────────────
//  Svelte Adapter
// ─────────────────────────────────────────────────────────────────────

class SvelteAdapter implements FrontendAdapter
{
    public function name(): string { return 'svelte'; }

    public function render(string $component, array $props = []): string
    {
        $propsJson = htmlspecialchars(json_encode($props, JSON_UNESCAPED_UNICODE), ENT_QUOTES);
        $id        = 'Libxa-svelte-' . md5($component . serialize($props));

        return <<<HTML
        <div id="{$id}" data-Libxa-svelte="{$component}" data-props="{$propsJson}"></div>
        HTML;
    }

    public function headTags(): string
    {
        return \Libxa\Frontend\ViteManifest::tags(['resources/js/app.js']);
    }

    public function bodyTags(): string { return ''; }

    public function viteEntries(): array { return ['resources/js/app.js']; }

    public function vitePluginConfig(): string
    {
        return "import { svelte } from '@sveltejs/vite-plugin-svelte';\n// plugins: [svelte()]";
    }

    public function npmDependencies(): array
    {
        return ['svelte', '@sveltejs/vite-plugin-svelte'];
    }
}

// ─────────────────────────────────────────────────────────────────────
//  Alpine.js Adapter
// ─────────────────────────────────────────────────────────────────────

class AlpineAdapter implements FrontendAdapter
{
    public function name(): string { return 'alpine'; }

    public function render(string $component, array $props = []): string
    {
        // Alpine is declarative — render a Blade view with x-data
        return Application::getInstance()->make('blade')->render($component, $props);
    }

    public function headTags(): string
    {
        return \Libxa\Frontend\ViteManifest::tags(['resources/css/app.css']);
    }

    public function bodyTags(): string
    {
        // Alpine can be loaded from CDN or npm
        $app = Application::getInstance();
        $env = $app?->env('APP_ENV', 'local');

        if ($env === 'local') {
            return '<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>';
        }

        return \Libxa\Frontend\ViteManifest::tags(['resources/js/app.js']);
    }

    public function viteEntries(): array { return ['resources/js/app.js']; }

    public function vitePluginConfig(): string { return '// Alpine.js: no Vite plugin needed'; }

    public function npmDependencies(): array { return ['alpinejs']; }
}

// ─────────────────────────────────────────────────────────────────────
//  Inertia.js Adapter
// ─────────────────────────────────────────────────────────────────────

class InertiaAdapter implements FrontendAdapter
{
    public function name(): string { return 'inertia'; }

    /**
     * Render an Inertia page response.
     * On first visit: returns full HTML + hydration data.
     * On subsequent XHR: returns JSON.
     */
    public function render(string $component, array $props = []): string
    {
        $request = Application::getInstance()?->make(\Libxa\Http\Request::class);

        $page = [
            'component' => $component,
            'props'     => $props,
            'url'       => $request?->fullUrl() ?? '/',
            'version'   => md5('Libxa-inertia-v1'),
        ];

        // Inertia XHR request — return JSON
        if ($request?->header('X-Inertia') === 'true') {
            header('Content-Type: application/json');
            header('X-Inertia: true');
            echo json_encode($page, JSON_UNESCAPED_UNICODE);
            exit;
        }

        // First visit — render full HTML layout
        $pageJson = htmlspecialchars(json_encode($page, JSON_UNESCAPED_UNICODE), ENT_QUOTES);

        return Application::getInstance()->make('blade')->render('layouts.inertia', [
            'page' => $page,
        ]);
    }

    public function headTags(): string
    {
        return \Libxa\Frontend\ViteManifest::tags(['resources/js/app.jsx', 'resources/css/app.css']);
    }

    public function bodyTags(): string { return ''; }

    public function viteEntries(): array { return ['resources/js/app.jsx']; }

    public function vitePluginConfig(): string
    {
        return "import react from '@vitejs/plugin-react';\n// plugins: [react()]";
    }

    public function npmDependencies(): array
    {
        return ['@inertiajs/react', 'react', 'react-dom', '@vitejs/plugin-react'];
    }
}
