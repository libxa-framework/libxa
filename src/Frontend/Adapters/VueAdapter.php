<?php

declare(strict_types=1);

namespace Libxa\Frontend\Adapters;

use Libxa\Frontend\Contracts\FrontendAdapter;
use Libxa\Foundation\Application;

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
