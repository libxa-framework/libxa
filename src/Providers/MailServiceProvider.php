<?php

declare(strict_types=1);

namespace Libxa\Providers;

use Libxa\Container\ServiceProvider;
use Libxa\Mail\MailManager;

class MailServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     */
    public function register(): void
    {
        $this->app->singleton('mail', function ($app) {
            return new MailManager($app);
        });

        $this->app->alias('mail', MailManager::class);
    }
}
