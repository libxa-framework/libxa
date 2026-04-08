<?php

declare(strict_types=1);

namespace Libxa\Providers;

use Libxa\Container\ServiceProvider;
use Libxa\Broadcasting\BroadcastManager;

class BroadcastServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     */
    public function register(): void
    {
        $this->app->singleton('broadcast', function ($app) {
            return new BroadcastManager($app);
        });

        $this->app->alias('broadcast', BroadcastManager::class);
    }
}
