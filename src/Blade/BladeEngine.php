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
 *   @extends, @section, @yield, @include, @includeIf, @includeWhen, @each
 *   @if, @elseif, @else, @endif, @unless, @isset
 *   @foreach, @forelse, @for, @while
 *   @component, @slot, @props
 *   @push, @prepend, @stack
 *   @verbatim
 *   @react, @vue, @svelte (frontend adapter directives)
 *   @reactive (Workerman reactive components)
 *   @auth, @guest
 *   @env, @production
 *   {{ }} - escaped output  |  {!! !!} - raw output
 *
 * STABILITY GUARANTEES
 *   - Output buffers opened during a render (by @section/@component/etc.)
 *     are always fully unwound, even when the view throws — this matters
 *     a lot on persistent-process runtimes (see src/Reactive/WsServer.php)
 *     where a leaked ob level would otherwise poison every later request
 *     handled by that worker.
 *   - Compiled cache files are written atomically (temp file + rename) so
 *     concurrent first-requests for the same view can never produce a
 *     half-written, unparsable cache file.
 *   - @include / @each / component recursion is depth-limited so a
 *     circular reference produces a clear exception instead of a fatal
 *     stack overflow / worker crash.
 *   - View content stacks (@push/@stack) are flushed at the start of
 *     every top-level render() call so nothing can leak between requests.
 */
class BladeEngine
{
    protected string $cachePath;
    /** @var array<string, string[]> namespace => list of base paths (checked in order) */
    protected array  $namespaces  = [];
    protected array  $viewPaths   = [];
    protected array  $composers   = [];
    protected Compiler $compiler;

    /** Set to false to force a recompile on every render (useful in local dev). */
    protected bool $cacheEnabled = true;

    /**
     * When true (production/"frozen" mode), the engine trusts an existing
     * compiled cache file completely and skips the filemtime() staleness
     * check on every render. This removes 2 stat() syscalls per view (per
     * @include/@component/render() call) which matters a lot on templates
     * that pull in many partials. Enable via freeze()/BladeServiceProvider
     * once `view:cache` (or a normal warm-up request) has produced fresh
     * compiled files; remember to view:clear + re-warm after deploys.
     */
    protected bool $checkTimestamps = true;

    /**
     * In-process memoization so that rendering the same view/partial many
     * times in a single request (e.g. a component inside a @foreach loop)
     * only ever resolves its path and validates its cache once, instead of
     * repeating the directory-walk + is_file() probing and the mtime
     * comparison on every single iteration.
     *
     * @var array<string, string> view name => resolved absolute file path
     */
    protected array $resolvedPathCache = [];

    /**
     * @var array<string, string> resolved file path => validated compiled cache path
     */
    protected array $compiledPathCache = [];

    /** Depth guard against runaway/circular @include chains. */
    protected int $renderDepth = 0;
    protected int $maxRenderDepth = 64;

    public function __construct(
        protected string $viewsPath,
        string           $cachePath = '',
    ) {
        $this->cachePath = $cachePath ?: sys_get_temp_dir() . '/Libxa_blade_cache';
        $this->compiler  = new Compiler();

        $this->ensureCacheDirectory();

        $this->viewPaths[] = rtrim($viewsPath, '/\\');
    }

    protected function ensureCacheDirectory(): void
    {
        if (! is_dir($this->cachePath) && ! @mkdir($this->cachePath, 0755, true) && ! is_dir($this->cachePath)) {
            throw new \RuntimeException("Unable to create Blade cache directory [{$this->cachePath}]. Check filesystem permissions.");
        }

        if (! is_writable($this->cachePath)) {
            throw new \RuntimeException("Blade cache directory [{$this->cachePath}] is not writable.");
        }
    }

    public function setCacheEnabled(bool $enabled): void
    {
        $this->cacheEnabled = $enabled;

        // Toggling the cache back on/off invalidates anything we'd already
        // decided was "fresh" this process, since dev-mode edits may have
        // happened while it was off.
        $this->compiledPathCache = [];
    }

    /**
     * Enable "frozen" production mode: once a compiled cache file exists
     * for a view, trust it for the lifetime of this process and never
     * stat() the source file again. This is the single biggest win for
     * view render speed under real traffic, especially combined with
     * `php libxa view:cache` to pre-warm every view before the first
     * request ever hits it.
     *
     * Do NOT enable this in local development — template edits won't be
     * picked up until the cache is cleared (`view:clear`).
     */
    public function freeze(): void
    {
        $this->checkTimestamps = false;
    }

    public function unfreeze(): void
    {
        $this->checkTimestamps = true;
        $this->compiledPathCache = [];
    }

    // ─────────────────────────────────────────────────────────────────
    //  Rendering
    // ─────────────────────────────────────────────────────────────────

    public function render(string $view, array $data = []): string
    {
        $path      = $this->resolvePath($view);
        $cachePath = $this->getOrCompile($path, $view);

        return $this->evaluateView($cachePath, $data);
    }

    /**
     * Render a raw Blade template string.
     */
    public function renderString(string $source, array $data = []): string
    {
        $cachePath = $this->cachePath . '/string_' . sha1($source) . '.php';

        if (! $this->cacheEnabled || ! is_file($cachePath)) {
            $this->compileToCache($source, $cachePath, 'inline string');
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
        $cachePath = $this->getOrCompile($path, $layout);

        // Merge child vars with sections so @yield works inside layout.
        // $__sections from the child is authoritative; don't let a stray
        // same-named var in $childVars silently overwrite it.
        unset($childVars['__sections']);
        $data = array_merge($childVars, ['__sections' => $sections]);

        return $this->evaluateView($cachePath, $data);
    }

    /**
     * Compile $path to a cache file if the cache is missing/stale, and
     * return the cache file path. Writes are atomic (temp file + rename)
     * so concurrent requests can never observe a half-written file.
     */
    protected function getOrCompile(string $path, string $viewNameForErrors): string
    {
        if (! $this->cacheEnabled) {
            $cachePath = $this->getCachedPath($path);
            $this->compileFileToCache($path, $cachePath, $viewNameForErrors);
            return $cachePath;
        }

        // Fast path: this exact view was already resolved+validated earlier
        // in this process (e.g. a partial/component re-used across a loop,
        // or a persistent Reactive worker that's already served it once
        // this "epoch"). Skip both stat() calls entirely.
        if (isset($this->compiledPathCache[$path])) {
            return $this->compiledPathCache[$path];
        }

        $cachePath = $this->getCachedPath($path);

        // Frozen/production mode: once the compiled file exists, trust it
        // for the rest of the process lifetime — no filemtime() calls at
        // all. Views are precompiled ahead of time via `view:cache`.
        if (! $this->checkTimestamps) {
            if (! is_file($cachePath)) {
                $this->compileFileToCache($path, $cachePath, $viewNameForErrors);
            }
            return $this->compiledPathCache[$path] = $cachePath;
        }

        $sourceMTime = @filemtime($path);
        $cacheMTime  = is_file($cachePath) ? @filemtime($cachePath) : false;

        // Recompile if the cache is missing, or the source is newer than
        // (or exactly as new as — same-second edits — the cache: we treat
        // ">=" rather than ">" to avoid the classic same-second staleness
        // bug on fast successive edits/deploys).
        if ($cacheMTime === false || $sourceMTime === false || $sourceMTime >= $cacheMTime) {
            $this->compileFileToCache($path, $cachePath, $viewNameForErrors);
        }

        return $this->compiledPathCache[$path] = $cachePath;
    }

    protected function compileFileToCache(string $path, string $cachePath, string $viewNameForErrors): void
    {
        $source = @file_get_contents($path);
        if ($source === false) {
            throw new \RuntimeException("Unable to read view file for [{$viewNameForErrors}] at [{$path}].");
        }

        $this->compileToCache($source, $cachePath, $viewNameForErrors);
    }

    protected function compileToCache(string $source, string $cachePath, string $viewNameForErrors): void
    {
        try {
            $compiled = $this->compiler->compile($source);
        } catch (\Throwable $e) {
            throw new \RuntimeException(
                "Failed to compile view [{$viewNameForErrors}]: {$e->getMessage()}",
                previous: $e
            );
        }

        // Atomic write: write to a unique temp file in the same directory
        // (so rename() stays on the same filesystem/volume) then rename
        // over the destination. rename() is atomic on POSIX filesystems,
        // so a concurrent reader either sees the old file or the fully
        // written new one — never a partial write.
        $tmpPath = $cachePath . '.' . bin2hex(random_bytes(6)) . '.tmp';

        if (@file_put_contents($tmpPath, $compiled) === false) {
            throw new \RuntimeException("Unable to write Blade cache file at [{$tmpPath}]. Check permissions on [{$this->cachePath}].");
        }

        if (! @rename($tmpPath, $cachePath)) {
            @unlink($tmpPath);
            throw new \RuntimeException("Unable to finalize Blade cache file at [{$cachePath}].");
        }

        // Push the freshly written file straight into OPcache's bytecode
        // cache. Without this, the file sits on disk until the *next*
        // include() triggers PHP to parse+compile it — this just does
        // that work once, up front, instead of on the request that's
        // already waiting on it.
        if (function_exists('opcache_compile_file') && function_exists('opcache_is_script_cached')) {
            try {
                if (! @opcache_is_script_cached($cachePath)) {
                    @opcache_compile_file($cachePath);
                }
            } catch (\Throwable) {
                // OPcache misconfiguration (e.g. opcache.restrict_api)
                // should never break a render — it's a pure optimization.
            }
        }
    }

    /**
     * Evaluate a compiled view file in an isolated scope.
     *
     * Internal variables are prefixed with __ to avoid collision with
     * user-provided view data (mirrors Laravel's PhpEngine approach).
     */
    protected function evaluateView(string $__path, array $__data): string
    {
        if ($this->renderDepth === 0) {
            // Fresh entry into the view layer (not a nested @include or
            // @extends call): guarantee no @push/@stack content survives
            // from a previous, unrelated render — important on
            // persistent-process runtimes where BladeStack is static.
            BladeStack::flush();

            // Drop the per-render memoization caches too, unless we're
            // frozen (production): on a persistent-process runtime (Swoole/
            // Workerman workers) a previous request may have resolved a
            // view that has since changed on disk, so a *new* top-level
            // request must re-validate at least once. Within the render
            // tree of a single request (partials/components reused in a
            // loop) the cache stays warm the whole time, which is where
            // the actual savings come from.
            if ($this->checkTimestamps) {
                $this->resolvedPathCache = [];
                $this->compiledPathCache = [];
            }
        }

        $this->renderDepth++;
        if ($this->renderDepth > $this->maxRenderDepth) {
            $this->renderDepth = 0;
            throw new \RuntimeException(
                "Blade render depth exceeded ({$this->maxRenderDepth}). ".
                "This usually means a view @includes or @extends itself, directly or indirectly."
            );
        }

        // Merge globally shared data (BladeEngine::share() / SharedData) in
        // underneath the per-render $__data, so explicit render() data always
        // wins over a shared default. This is what makes View::share()-style
        // globals actually reach the template — previously SharedData was
        // written to but never read back here, so anything relying on a
        // shared value (including the $errors bag below) was always null.
        $__data = array_replace(SharedData::all(), $__data);

        // The validation error bag ($errors) is expected to be available in
        // *every* view, even when nothing failed validation and no
        // controller explicitly passed it in (mirrors Laravel's behavior).
        // Without this, `@if($errors->any())` in a view fatals with
        // "Call to a member function any() on null" the first time it's
        // rendered without an explicit $errors variable.
        if (! array_key_exists('errors', $__data)) {
            $__data['errors'] = function_exists('errors') ? errors() : new \Libxa\Validation\MessageBag();
        }

        // extract() populates $__sections directly when renderWithSections()
        // passed one along in $__data; otherwise the compiled template's own
        // "$__sections = $__sections ?? []" header initializes it safely.
        extract($__data, EXTR_SKIP);

        $__obBaselineOuter = ob_get_level();
        ob_start();

        try {
            include $__path;
            $output = ob_get_clean();
        } catch (\Throwable $e) {
            // Unwind ANY buffer levels opened while rendering this view
            // (including nested @section/@component buffers left open by
            // the exception) back down to where we started, so a failure
            // deep inside a template can never leak into the next request
            // handled by this PHP process/worker.
            while (ob_get_level() > $__obBaselineOuter) {
                ob_end_clean();
            }

            throw $e;
        } finally {
            $this->renderDepth = max(0, $this->renderDepth - 1);
        }

        return $output ?: '';
    }

    // ─────────────────────────────────────────────────────────────────
    //  View path resolution
    // ─────────────────────────────────────────────────────────────────

    public function resolvePath(string $view): string
    {
        if (isset($this->resolvedPathCache[$view])) {
            return $this->resolvedPathCache[$view];
        }

        return $this->resolvedPathCache[$view] = $this->resolvePathUncached($view);
    }

    protected function resolvePathUncached(string $view): string
    {
        if (str_contains($view, '::')) {
            [$namespace, $name] = explode('::', $view, 2);

            if (! isset($this->namespaces[$namespace])) {
                throw new \RuntimeException(
                    "View namespace [{$namespace}] is not registered. ".
                    "Register it with \$blade->addNamespace('{$namespace}', \$path) before use."
                );
            }

            $relative = str_replace('.', DIRECTORY_SEPARATOR, $name) . '.blade.php';

            foreach ($this->namespaces[$namespace] as $basePath) {
                $full = $basePath . DIRECTORY_SEPARATOR . $relative;
                if (is_file($full)) {
                    return $full;
                }
            }

            throw new \RuntimeException(
                "View [{$view}] not found in namespace [{$namespace}]. Searched: " .
                implode(', ', $this->namespaces[$namespace])
            );
        }

        $relative = str_replace('.', DIRECTORY_SEPARATOR, $view) . '.blade.php';

        foreach ($this->viewPaths as $basePath) {
            $full = $basePath . DIRECTORY_SEPARATOR . $relative;
            if (is_file($full)) {
                return $full;
            }
        }

        throw new \RuntimeException("View [{$view}] not found. Searched: " . implode(', ', $this->viewPaths));
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

    /**
     * Register a namespace. Can be called multiple times for the same
     * namespace to register additional fallback paths (checked in the
     * order they were added) — matches Laravel's addNamespace() behavior
     * where modules/packages can layer views on top of each other.
     */
    public function addNamespace(string $namespace, string|array $path): void
    {
        $paths = is_array($path) ? $path : [$path];
        $paths = array_map(fn($p) => rtrim($p, '/\\'), $paths);

        $this->namespaces[$namespace] = array_merge($this->namespaces[$namespace] ?? [], $paths);
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
     * Compile every *.blade.php file reachable from the registered view
     * paths and namespaces, ahead of time. Used by `php libxa view:cache`
     * so the first real request never pays a compile cost, and so the app
     * can safely run in frozen (checkTimestamps=false) mode afterwards.
     *
     * @return string[] absolute source paths that were compiled
     */
    public function precompileAll(): array
    {
        $compiled = [];

        $allBasePaths = $this->viewPaths;
        foreach ($this->namespaces as $paths) {
            $allBasePaths = array_merge($allBasePaths, $paths);
        }

        foreach (array_unique($allBasePaths) as $basePath) {
            if (! is_dir($basePath)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($basePath, \FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.blade.php')) {
                    continue;
                }

                $path      = $file->getPathname();
                $cachePath = $this->getCachedPath($path);
                $this->compileFileToCache($path, $cachePath, $path);
                $compiled[] = $path;
            }
        }

        return $compiled;
    }

    /**
     * Clear all compiled view cache.
     */
    public function clearCache(): int
    {
        $files = glob($this->cachePath . '/*.php') ?: [];
        $count = 0;
        foreach ($files as $file) {
            if (@unlink($file)) {
                $count++;
            }
        }
        return $count;
    }
}
