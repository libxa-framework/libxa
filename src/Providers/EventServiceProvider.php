<?php

declare(strict_types=1);

namespace Libxa\Providers;

use Libxa\Container\ServiceProvider;
use Libxa\Events\EventBus;

class EventServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('events', function ($app) {
            return new EventBus();
        });
    }
}
