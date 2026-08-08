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
        // Register Encrypter. The Encrypter itself now understands the
        // "base64:" prefix and validates the key length, so the decoding that
        // used to live here (and silently produced an unusable key when the
        // base64 was malformed) is gone.
        $this->app->singleton('encrypter', function ($app) {
            $key = (string) $app::env('APP_KEY', '');

            if ($key === '') {
                throw new \RuntimeException(
                    'No application key set. Add APP_KEY to your .env file '
                    . 'or run `php libxa key:generate`.'
                );
            }

            $cipher = (string) ($app->config('app.cipher') ?? 'AES-256-CBC');

            return new Encrypter($key, $cipher);
        });

        $this->app->alias('encrypter', Encrypter::class);

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
