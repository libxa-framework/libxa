<?php

declare(strict_types=1);

namespace Libxa\Providers;

use Libxa\Container\ServiceProvider;
use Libxa\Auth\AuthManager;

class AuthServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AuthManager::class, function ($app) {
            return new AuthManager($app);
        });

        $this->app->alias(AuthManager::class, 'auth');
    }

    public function boot(): void
    {
        // Add auth-related view composers or events here
    }
}
