<?php

declare(strict_types=1);

namespace Libxa\Frontend\Adapters;

use Libxa\Frontend\Contracts\FrontendAdapter;
use Libxa\Foundation\Application;

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
