<?php

declare(strict_types=1);

namespace Libxa\Providers;

use Libxa\Container\ServiceProvider;
use Libxa\Cache\CacheManager;

class CacheServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     */
    public function register(): void
    {
        $this->app->singleton('cache', function ($app) {
            return new CacheManager($app);
        });

        $this->app->alias('cache', CacheManager::class);
    }
}
