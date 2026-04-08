<?php

declare(strict_types=1);

namespace Libxa\Providers;

use Libxa\Container\ServiceProvider;
use Libxa\Router\Router;
use Libxa\Http\Controllers\PulseController;

/**
 * LibxaPulse Service Provider
 *
 * Registers the monitoring dashboard routes.
 */
class PulseServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Bindings can go here
    }

    public function boot(): void
    {
        /** @var Router $router */
        $router = $this->app->make('router');

        // Monitoring Dashboard (Developer Only)
        $router->group(['prefix' => 'pulse', 'middleware' => 'web'], function (Router $router) {
            $router->get('/', [PulseController::class, 'index']);
            $router->get('/stats', [PulseController::class, 'stats']);
        });
    }
}
