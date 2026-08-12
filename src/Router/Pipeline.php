<?php

declare(strict_types=1);

namespace Libxa\Router;

use Libxa\Http\Request;
use Libxa\Http\Response;
use Libxa\Foundation\Application;

/**
 * Middleware Pipeline
 *
 * Wraps the route handler with an ordered stack of middleware.
 * Each middleware receives the request and a "next" callable.
 *
 * Stability notes:
 *  - Middleware *groups* ('web', 'api') are now expanded. Previously only
 *    aliases were resolved, so Route::middleware('web') asked the container
 *    to build a class literally named "web" and died with
 *    "Target class [web] does not exist."
 *  - A pipe may be a class name, an alias, a group name, a closure or an
 *    already-constructed middleware object. The old signature was `string`
 *    only, so passing a closure raised a TypeError inside array_reduce.
 *  - A middleware that returns something other than a Response now produces
 *    a message naming the offending middleware instead of an opaque TypeError.
 */
class Pipeline
{
    protected ?Request $passable = null;
    protected array    $pipes    = [];

    /** Fallback alias map used when HttpKernel is not available */
    protected static array $aliases = [
        'auth'     => \Libxa\Http\Middleware\AuthMiddleware::class,
        'guest'    => \Libxa\Http\Middleware\GuestMiddleware::class,
        'throttle' => \Libxa\Http\Middleware\ThrottleMiddleware::class,
        'verified' => \Libxa\Http\Middleware\EmailVerifiedMiddleware::class,
    ];

    /** Fallback middleware groups used when HttpKernel is not available */
    protected static array $groups = [
        'web' => [
            \Libxa\Http\Middleware\SessionMiddleware::class,
            \Libxa\Http\Middleware\ShareErrorsMiddleware::class,
        ],
        'api' => [
            \Libxa\Http\Middleware\ThrottleMiddleware::class . ':60',
        ],
    ];

    public function __construct(protected Application $app) {}

    public function send(Request $request): static
    {
        $this->passable = $request;
        return $this;
    }

    public function through(array $middleware): static
    {
        $this->pipes = $this->expandGroups($middleware);
        return $this;
    }

    public function then(\Closure $destination): Response
    {
        if ($this->passable === null) {
            throw new \LogicException('Pipeline::then() called before send().');
        }

        $pipeline = array_reduce(
            array_reverse($this->pipes),
            $this->carry(),
            $destination
        );

        return $pipeline($this->passable);
    }

    /**
     * Flatten group names into their constituent middleware, de-duplicating
     * along the way so a route in the 'web' group that also lists
     * SessionMiddleware explicitly does not start the session twice.
     */
    protected function expandGroups(array $middleware, int $depth = 0): array
    {
        if ($depth > 10) {
            throw new \RuntimeException('Middleware group nesting is too deep (circular group?).');
        }

        $groups   = $this->groups();
        $resolved = [];

        foreach ($middleware as $pipe) {
            if (is_string($pipe) && isset($groups[$pipe])) {
                $resolved = array_merge($resolved, $this->expandGroups((array) $groups[$pipe], $depth + 1));
                continue;
            }

            $resolved[] = $pipe;
        }

        return $this->unique($resolved);
    }

    /**
     * Remove duplicate pipes while preserving order and leaving
     * non-string pipes (closures, objects) untouched.
     */
    protected function unique(array $pipes): array
    {
        $seen = [];
        $out  = [];

        foreach ($pipes as $pipe) {
            if (! is_string($pipe)) {
                $out[] = $pipe;
                continue;
            }

            if (isset($seen[$pipe])) {
                continue;
            }

            $seen[$pipe] = true;
            $out[]       = $pipe;
        }

        return $out;
    }

    protected function carry(): \Closure
    {
        return function (\Closure $stack, mixed $pipe): \Closure {
            return function (Request $passable) use ($stack, $pipe): Response {
                $response = $this->invokePipe($pipe, $passable, $stack);

                if (! $response instanceof Response) {
                    $name = is_object($pipe) ? $pipe::class : (is_string($pipe) ? $pipe : 'Closure');

                    throw new \RuntimeException(
                        "Middleware [{$name}] must return a " . Response::class
                        . ', got ' . get_debug_type($response) . '.'
                    );
                }

                return $response;
            };
        };
    }

    protected function invokePipe(mixed $pipe, Request $passable, \Closure $stack): mixed
    {
        if ($pipe instanceof \Closure) {
            return $pipe($passable, $stack);
        }

        if (is_object($pipe)) {
            return $pipe->handle($passable, $stack);
        }

        [$class, $args] = $this->parsePipe((string) $pipe);

        $middleware = $this->app->make($class);

        if (! method_exists($middleware, 'handle')) {
            throw new \RuntimeException("Middleware [{$class}] has no handle() method.");
        }

        return $middleware->handle($passable, $stack, ...$args);
    }

    protected function parsePipe(string $pipe): array
    {
        $class = $pipe;
        $args  = [];

        if (str_contains($pipe, ':')) {
            [$class, $argStr] = explode(':', $pipe, 2);
            $args = explode(',', $argStr);
        }

        // Resolve from static alias map first (avoids circular resolution)
        if (isset(static::$aliases[$class])) {
            return [static::$aliases[$class], $args];
        }

        // Try kernel aliases (without causing circular dependency)
        try {
            if ($this->app->has(\Libxa\Foundation\HttpKernel::class)) {
                $kernel  = $this->app->make(\Libxa\Foundation\HttpKernel::class);
                $aliases = $kernel->getMiddlewareAliases();
                if (isset($aliases[$class])) {
                    return [$aliases[$class], $args];
                }
            }
        } catch (\Throwable) {
            // Ignore: fall through to the raw class name
        }

        if (! class_exists($class)) {
            throw new \RuntimeException(
                "Middleware [{$class}] is not a known alias, group or class."
            );
        }

        return [$class, $args];
    }

    /**
     * Middleware groups, preferring the kernel's definitions when available.
     */
    protected function groups(): array
    {
        try {
            if ($this->app->has(\Libxa\Foundation\HttpKernel::class)) {
                $groups = $this->app->make(\Libxa\Foundation\HttpKernel::class)->getMiddlewareGroups();

                if (is_array($groups) && $groups !== []) {
                    return $groups;
                }
            }
        } catch (\Throwable) {
            // Fall back to the static map below.
        }

        return static::$groups;
    }

    /**
     * Register additional middleware aliases at runtime.
     */
    public static function addAlias(string $alias, string $class): void
    {
        static::$aliases[$alias] = $class;
    }

    /**
     * Register (or replace) a middleware group at runtime.
     */
    public static function addGroup(string $name, array $middleware): void
    {
        static::$groups[$name] = $middleware;
    }
}
