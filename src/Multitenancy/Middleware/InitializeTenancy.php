<?php

declare(strict_types=1);

namespace Libxa\Multitenancy\Middleware;

use Libxa\Http\Request;
use Libxa\Http\Response;
use Libxa\Multitenancy\TenancyManager;
use Libxa\Foundation\Application;

class InitializeTenancy
{
    /**
     * Handle an incoming request and boot tenancy if enabled.
     */
    public function handle(Request $request, \Closure $next): Response
    {
        $app = Application::getInstance();

        // Silently skip if tenancy is not enabled (avoids boot crashes)
        $enabled = $app?->env('TENANCY_ENABLED', 'false');
        if ($enabled !== 'true' && $enabled !== '1') {
            return $next($request);
        }

        try {
            $manager = new TenancyManager($app);
            $manager->initialize($request);
            $app->instance(TenancyManager::class, $manager);
            $app->instance('tenant', $manager);
        } catch (\Throwable $e) {
            // Tenancy boot failure should not kill the whole request in non-strict mode
            if ($app->config('app.debug') || $app->env('APP_DEBUG') === 'true') {
                throw $e;
            }
        }

        return $next($request);
    }
}
