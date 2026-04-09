<?php

declare(strict_types=1);

namespace Libxa\Providers;

use Libxa\Ai\AiManager;
use Libxa\Container\ServiceProvider;

class AiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('ai', function ($app) {
            return new AiManager($app);
        });

        $this->app->alias('ai', AiManager::class);
    }
}
