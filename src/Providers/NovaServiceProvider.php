<?php

declare(strict_types=1);

namespace Libxa\Providers;

use Libxa\Container\ServiceProvider;
use Libxa\Nova\ResourceManager;
use Libxa\Router\Route;

class NovaServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     */
    public function register(): void
    {
        $this->app->singleton('nova', function ($app) {
            $manager = new ResourceManager($app);
            $manager->discover($app->basePath('app/Nova'));

            return $manager;
        });

        $this->app->alias('nova', ResourceManager::class);

        $this->registerRoutes();
    }

    /**
     * Register the admin routes.
     */
    protected function registerRoutes(): void
    {
        $router = $this->app->make('router');
        $prefix = $this->app->config('nova.prefix', 'admin');

        $router->group(['prefix' => $prefix], function ($router) {
            $router->get('/', [\Libxa\Nova\Http\Controllers\NovaController::class, 'dashboard'])->name('nova.dashboard');

            $router->group(['prefix' => 'resources'], function ($router) {
                $router->get('/{resource}', [\Libxa\Nova\Http\Controllers\NovaController::class, 'index'])->name('nova.index');
                $router->get('/{resource}/create', [\Libxa\Nova\Http\Controllers\NovaController::class, 'create'])->name('nova.create');
                $router->post('/{resource}', [\Libxa\Nova\Http\Controllers\NovaController::class, 'store'])->name('nova.store');
                $router->get('/{resource}/{id}/edit', [\Libxa\Nova\Http\Controllers\NovaController::class, 'edit'])->name('nova.edit');
                $router->put('/{resource}/{id}', [\Libxa\Nova\Http\Controllers\NovaController::class, 'update'])->name('nova.update');
                $router->delete('/{resource}/{id}', [\Libxa\Nova\Http\Controllers\NovaController::class, 'destroy'])->name('nova.destroy');
            });
        });
    }
}
