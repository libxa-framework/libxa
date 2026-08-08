<?php

declare(strict_types=1);

namespace Libxa\Frontend\Adapters;

use Libxa\Frontend\Contracts\FrontendAdapter;
use Libxa\Foundation\Application;

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
