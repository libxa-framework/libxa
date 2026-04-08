<?php

declare(strict_types=1);

namespace Libxa\Providers;

use Libxa\Container\ServiceProvider;
use Libxa\Session\Session;

class SessionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('session', function () {
            return new Session();
        });
    }

    public function boot(): void
    {
        // Session aging flash data
        if ($this->app->has('session')) {
            $this->app->make('session')->ageFlashData();
        }
    }
}
