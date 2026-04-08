<?php

declare(strict_types=1);

namespace Libxa\Providers;

use Libxa\Container\ServiceProvider;
use Libxa\Queue\QueueManager;

class QueueServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     */
    public function register(): void
    {
        $this->app->singleton('queue', function ($app) {
            return new QueueManager($app);
        });

        $this->app->alias('queue', QueueManager::class);
    }
}
