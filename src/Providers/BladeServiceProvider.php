<?php

declare(strict_types=1);

namespace Libxa\Providers;

use Libxa\Container\ServiceProvider;
use Libxa\Blade\BladeEngine;

class BladeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('blade', function ($app) {
            $viewsPath = $app->viewPath();
            $cachePath = $app->storagePath('framework/views');
            
            return new BladeEngine($viewsPath, $cachePath);
        });
    }

    public function boot(): void
    {
        // Add shared data or custom directives if needed
    }
}
