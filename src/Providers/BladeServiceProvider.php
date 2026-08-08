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

            $blade = new BladeEngine($viewsPath, $cachePath);

            // In production, skip the per-render filemtime() staleness
            // check entirely once a compiled view exists — templates don't
            // change without a deploy, and a deploy should run
            // `php libxa view:cache` (which also clears stale entries),
            // so trusting the cache outright is safe and meaningfully
            // faster under real traffic. Local/dev keeps live-reload
            // behavior so template edits show up on refresh.
            if (($app->has('config') ? $app->make('config')?->get('app.env', 'production') : \Libxa\Foundation\Application::env('APP_ENV', 'production')) === 'production') {
                $blade->freeze();
            }

            return $blade;
        });
    }

    public function boot(): void
    {
        // Add shared data or custom directives if needed
    }
}
