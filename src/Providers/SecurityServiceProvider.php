<?php

declare(strict_types=1);

namespace Libxa\Providers;

use Libxa\Container\ServiceProvider;
use Libxa\Security\Encrypter;
use Libxa\Auth\Access\Gate;
use Libxa\Multitenancy\TenantManager;

class SecurityServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Register Encrypter
        $this->app->singleton('encrypter', function ($app) {
            $key = $app->env('APP_KEY');
            
            if (str_starts_with((string)$key, 'base64:')) {
                $key = base64_decode(substr($key, 7));
            }

            return new Encrypter((string)$key);
        });

        // Register Gate
        $this->app->singleton('gate', function ($app) {
            return new Gate($app);
        });

        // Register Tenant Manager
        $this->app->singleton('tenant', function ($app) {
            return new TenantManager($app);
        });
    }
}
