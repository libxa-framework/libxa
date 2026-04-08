<?php

declare(strict_types=1);

namespace Libxa\Providers;

use Libxa\Container\ServiceProvider;
use Libxa\Config\Config;

class ConfigServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('config', function ($app) {
            $config = new Config($app->configPath());
            $config->load();
            return $config;
        });
    }
}
