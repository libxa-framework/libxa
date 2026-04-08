<?php

declare(strict_types=1);

namespace Libxa\Providers;

use Libxa\Container\ServiceProvider;
use Libxa\Lang\Translator;

class LangServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('translator', function ($app) {
            return new Translator($app);
        });
    }

    public function boot(): void
    {
        // Optional: Pre-load locale from session if needed
    }
}
