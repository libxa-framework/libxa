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
 */
class Pipeline
{
    protected Request $passable;
    protected array   $pipes = [];

    public function __construct(protected Application $app) {}

    public function send(Request $request): static
    {
        $this->passable = $request;
        return $this;
    }

    public function through(array $middleware): static
    {
        $this->pipes = $middleware;
        return $this;
    }

    public function then(\Closure $destination): Response
    {
        $pipeline = array_reduce(
            array_reverse($this->pipes),
            $this->carry(),
            $destination
        );

        return $pipeline($this->passable);
    }

    protected function carry(): \Closure
    {
        return function (\Closure $stack, string $pipe): \Closure {
            return function (Request $passable) use ($stack, $pipe): Response {
                [$class, $args] = $this->parsePipe($pipe);

                $middleware = $this->app->make($class);

                return $middleware->handle($passable, $stack, ...$args);
            };
        };
    }

    protected function parsePipe(string $pipe): array
    {
        $class = $pipe;
        $args  = [];

        if (str_contains($pipe, ':')) {
            [$class, $argStr] = explode(':', $pipe, 2);
            $args = explode(',', $argStr);
        }

        // Resolve middleware alias mapping statically defined in HttpKernel
        if (class_exists(\Libxa\Foundation\HttpKernel::class)) {
            $kernel  = $this->app->make(\Libxa\Foundation\HttpKernel::class);
            $aliases = $kernel->getMiddlewareAliases();

            if (isset($aliases[$class])) {
                $class = $aliases[$class];
            }
        }

        return [$class, $args];
    }
}
