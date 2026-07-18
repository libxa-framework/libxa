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

        // WebSocket routing (WsRouter, ws.router, app/WebSockets scanning)
        // now lives in the optional libxa/socket package, not the core
        // framework — see SocketServiceProvider in that package.
    }

    public function boot(): void
    {
        $cacheFile = $this->app->basePath('src/bootstrap/cache/routes.php');

        if (file_exists($cacheFile)) {
            /** @var Router $router */
            $router = $this->app->make(Router::class);
            $router->loadCachedRoutes(require $cacheFile);
        } else {
            // Auto-load app routes
            $webRoutes = $this->app->basePath('src/routes/web.php');
            if (file_exists($webRoutes)) {
                $this->loadRoutesFrom($webRoutes);
            }

            $apiRoutes = $this->app->basePath('src/routes/api.php');
            if (file_exists($apiRoutes)) {
                $this->loadRoutesFrom($apiRoutes);
            }
        }
    }
}
