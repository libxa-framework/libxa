<?php

declare(strict_types=1);

namespace Libxa\Frontend\Adapters;

use Libxa\Frontend\Contracts\FrontendAdapter;
use Libxa\Foundation\Application;

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
