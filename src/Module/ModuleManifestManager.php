<?php

declare(strict_types=1);

namespace Libxa\Module;

use Libxa\Foundation\Application;

/**
 * Module Manifest Manager
 * 
 * Handles the generation and loading of module/package manifests
 * to avoid expensive disk scanning during the application boot sequence.
 */
class ModuleManifestManager
{
    protected string $manifestPath;

    public function __construct(protected Application $app, string $type = 'modules')
    {
        $this->manifestPath = $this->app->basePath("src/bootstrap/cache/{$type}.php");
    }

    /**
     * Load the manifest from cache.
     */
    public function load(): array
    {
        if (file_exists($this->manifestPath)) {
            return require $this->manifestPath;
        }

        return [];
    }

    /**
     * Save the manifest to cache.
     */
    public function save(array $manifest): void
    {
        $dir = dirname($this->manifestPath);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $content = '<?php' . PHP_EOL . PHP_EOL . 'return ' . var_export($manifest, true) . ';' . PHP_EOL;
        file_put_contents($this->manifestPath, $content);

        // Try to trigger opcache invalidation if available
        if (function_exists('opcache_invalidate')) {
            @opcache_invalidate($this->manifestPath, true);
        }
    }

    /**
     * Determine if the manifest needs rebuilding.
     */
    public function needsRebuild(string $sourcePath): bool
    {
        if (! file_exists($this->manifestPath)) {
            return true;
        }

        if ($this->app->isTesting() || $this->app->config('app.env') === 'local') {
            return filemtime($sourcePath) > filemtime($this->manifestPath);
        }

        return false;
    }

    /**
     * Build the manifest by scanning the source path.
     */
    public function discover(string $sourcePath): array
    {
        $manifest = [];
        $dirs = array_filter(glob("$sourcePath/*"), 'is_dir');

        foreach ($dirs as $dir) {
            $slug = basename($dir);
            $provider = $this->resolveProvider($dir);

            if ($provider) {
                $manifest[$slug] = [
                    'provider' => $provider,
                    'path' => $dir,
                    'requires' => $this->resolveDependencies($provider),
                ];
            }
        }

        $this->save($manifest);
        return $manifest;
    }

    protected function resolveProvider(string $dir): ?string
    {
        // For local modules, we expect a ServiceProvider in the root or a standard naming scheme
        // e.g. src/app/Modules/Billing/BillingServiceProvider.php
        $slug = basename($dir);
        $providerFile = $dir . DIRECTORY_SEPARATOR . $slug . 'ServiceProvider.php';

        if (! file_exists($providerFile)) {
            // Fallback to old Module.php for backward compatibility
            $providerFile = $dir . DIRECTORY_SEPARATOR . 'Module.php';
        }

        if (! file_exists($providerFile)) {
            return null;
        }

        return $this->extractClassName($providerFile);
    }

    protected function extractClassName(string $file): ?string
    {
        $src = file_get_contents($file);
        $namespace = '';
        if (preg_match('/namespace\s+(.+?);/i', $src, $matches)) {
            $namespace = $matches[1];
        }

        if (preg_match('/class\s+(\w+)\s+/i', $src, $matches)) {
            $class = $matches[1];
            return $namespace ? "$namespace\\$class" : $class;
        }

        return null;
    }

    protected function resolveDependencies(string $provider): array
    {
        if (! class_exists($provider)) {
            return [];
        }

        $instance = new $provider($this->app);
        if (method_exists($instance, 'requires')) {
            return $instance->requires();
        }

        return [];
    }
}
