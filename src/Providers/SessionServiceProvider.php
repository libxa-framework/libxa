<?php

declare(strict_types=1);

namespace Libxa\Providers;

use Libxa\Container\ServiceProvider;
use Libxa\Session\Session;

class SessionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('session', function ($app) {
            // config/session.php was never passed to the Session, so its
            // cookie settings (http_only, same_site, secure, lifetime) had no
            // effect at all.
            return new Session((array) ($app->config('session') ?? []));
        });

        $this->app->alias('session', Session::class);
    }

    public function boot(): void
    {
        // Flash data is aged by SessionMiddleware, once per request. Doing it
        // here as well consumed one-request flash messages before the view
        // that was supposed to display them ever ran.
    }
}
