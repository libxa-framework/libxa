<?php

declare(strict_types=1);

namespace Libxa\Frontend\Adapters;

use Libxa\Frontend\Contracts\FrontendAdapter;
use Libxa\Foundation\Application;

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
