<?php

declare(strict_types=1);

namespace Libxa\Blade;

/**
 * Blade-X Engine
 *
 * LibxaFrame's compiled template engine.
 * Compiles .blade.php files to cached PHP and renders them.
 *
 * Features:
 *   @extends, @section, @yield, @include, @each
 *   @if, @elseif, @else, @endif
 *   @foreach, @for, @while
 *   @component, @props, @slot
 *   @react, @vue, @svelte (frontend adapter directives)
 *   @reactive (Workerman reactive components)
 *   @auth, @guest
 *   @env, @production
 *   {{ }} - escaped output  |  {!! !!} - raw output
 */
class BladeEngine
{
    protected string $cachePath;
    protected array  $namespaces  = [];
    protected array  $viewPaths   = [];
    protected array  $composers   = [];
    protected Compiler $compiler;

    public function __construct(
        protected string $viewsPath,
        string           $cachePath = '',
    ) {
        $this->cachePath = $cachePath ?: sys_get_temp_dir() . '/Libxa_blade_cache';
        $this->compiler  = new Compiler();

        if (! is_dir($this->cachePath)) {
            mkdir($this->cachePath, 0755, true);
        }

        $this->viewPaths[] = $viewsPath;
    }

    // ─────────────────────────────────────────────────────────────────
    //  Rendering
    // ─────────────────────────────────────────────────────────────────

    public function render(string $view, array $data = []): string
    {
        $path      = $this->resolvePath($view);
        $cachePath = $this->getCachedPath($path);

        // Recompile if source changed or cache missing
        if (! file_exists($cachePath) || filemtime($path) > filemtime($cachePath)) {
            $source   = file_get_contents($path);
            $compiled = $this->compiler->compile($source);
            file_put_contents($cachePath, $compiled);
        }

        return $this->evaluateView($cachePath, $data);
    }

    /**
     * Render a raw Blade template string.
     */
    public function renderString(string $source, array $data = []): string
    {
        $cachePath = $this->cachePath . '/string_' . sha1($source) . '.php';

        if (! file_exists($cachePath)) {
            $compiled = $this->compiler->compile($source);
            file_put_contents($cachePath, $compiled);
        }

        return $this->evaluateView($cachePath, $data);
    }

    /**
     * Render a layout view with pre-captured sections from a child template.
     * Called by compiled child templates when they use @extends.
     */
    public function renderWithSections(string $layout, array $sections, array $childVars = []): string
    {
        $path      = $this->resolvePath($layout);
        $cachePath = $this->getCachedPath($path);

        // Recompile layout if needed
        if (! file_exists($cachePath) || filemtime($path) > filemtime($cachePath)) {
            $source   = file_get_contents($path);
            $compiled = $this->compiler->compile($source);
            file_put_contents($cachePath, $compiled);
        }

        // Merge child vars with sections so @yield works inside layout
        $data = array_merge($childVars, ['__sections' => $sections]);

        return $this->evaluateView($cachePath, $data);
    }

    /**
     * Evaluate a compiled view file in an isolated scope.
     *
     * Internal variables are prefixed with __ to avoid collision with
     * user-provided view data (mirrors Laravel's PhpEngine approach).
     */
    protected function evaluateView(string $__path, array $__data): string
    {
        extract($__data, EXTR_SKIP);

        ob_start();

        try {
            include $__path;
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }

        return ob_get_clean() ?: '';
    }

    // ─────────────────────────────────────────────────────────────────
    //  View path resolution
    // ─────────────────────────────────────────────────────────────────

    public function resolvePath(string $view): string
    {
        // Handle namespace: admin::users.index
        if (str_contains($view, '::')) {
            [$namespace, $name] = explode('::', $view, 2);

            if (isset($this->namespaces[$namespace])) {
                $file = $this->namespaces[$namespace] . '/' . str_replace('.', '/', $name) . '.blade.php';
                if (file_exists($file)) return $file;
            }
        }

        $relative = str_replace('.', DIRECTORY_SEPARATOR, $view) . '.blade.php';

        foreach ($this->viewPaths as $basePath) {
            $full = $basePath . DIRECTORY_SEPARATOR . $relative;
            if (file_exists($full)) return $full;
        }

        throw new \RuntimeException("View [$view] not found. Searched: " . implode(', ', $this->viewPaths));
    }

    protected function getCachedPath(string $path): string
    {
        return $this->cachePath . '/' . sha1($path) . '.php';
    }

    // ─────────────────────────────────────────────────────────────────
    //  API
    // ─────────────────────────────────────────────────────────────────

    public function exists(string $view): bool
    {
        try {
            $this->resolvePath($view);
            return true;
        } catch (\RuntimeException) {
            return false;
        }
    }

    public function addNamespace(string $namespace, string $path): void
    {
        $this->namespaces[$namespace] = rtrim($path, '/\\');
    }

    public function addPath(string $path): void
    {
        $this->viewPaths[] = rtrim($path, '/\\');
    }

    public function composer(string $view, \Closure $callback): void
    {
        $this->composers[$view] = $callback;
    }

    public function share(string $key, mixed $value): void
    {
        SharedData::set($key, $value);
    }

    public function getCompiler(): Compiler
    {
        return $this->compiler;
    }

    /**
     * Clear all compiled view cache.
     */
    public function clearCache(): int
    {
        $files = glob($this->cachePath . '/*.php') ?: [];
        foreach ($files as $file) unlink($file);
        return count($files);
    }
}

/**
 * Shared data store — works like View::share() in Laravel.
 */
class SharedData
{
    protected static array $data = [];

    public static function set(string $key, mixed $value): void
    {
        static::$data[$key] = $value;
    }

    public static function get(?string $key = null): mixed
    {
        return $key !== null ? (static::$data[$key] ?? null) : static::$data;
    }

    public static function all(): array { return static::$data; }
}
