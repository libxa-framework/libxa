<?php

declare(strict_types=1);

namespace Libxa\Frontend\Adapters;

use Libxa\Frontend\Contracts\FrontendAdapter;
use Libxa\Foundation\Application;

class AlpineAdapter implements FrontendAdapter
{
    public function name(): string { return 'alpine'; }

    public function render(string $component, array $props = []): string
    {
        // Alpine is declarative: render a Blade view with x-data
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
