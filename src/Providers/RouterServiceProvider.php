<?php

declare(strict_types=1);

namespace Libxa\Providers;

use Libxa\Container\ServiceProvider;
use Libxa\Router\Router;

class RouterServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Router::class, function ($app) {
            return new Router($app);
        });

        $this->app->alias(Router::class, 'router');

        $this->app->singleton(\Libxa\WebSockets\WsRouter::class, function ($app) {
            return new \Libxa\WebSockets\WsRouter($app);
        });

        $this->app->alias(\Libxa\WebSockets\WsRouter::class, 'ws.router');
    }

    public function boot(): void
    {
        // Auto-load app routes
        $webRoutes = $this->app->basePath('src/routes/web.php');
        if (file_exists($webRoutes)) {
            $this->loadRoutesFrom($webRoutes);
        }

        $apiRoutes = $this->app->basePath('src/routes/api.php');
        if (file_exists($apiRoutes)) {
            $this->loadRoutesFrom($apiRoutes);
        }

        // Auto-scan WebSocket channels
        $wsPath = $this->app->basePath('src/app/WebSockets');
        if (is_dir($wsPath)) {
            /** @var \Libxa\WebSockets\WsRouter $wsRouter */
            $wsRouter = $this->app->make('ws.router');
            $wsRouter->scanDirectory($wsPath, 'App\\WebSockets');
        }
    }
}
