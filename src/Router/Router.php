<?php

declare(strict_types=1);

namespace Libxa\Router;

use Libxa\Http\Request;
use Libxa\Http\Response;
use Libxa\Foundation\Application;

/**
 * LibxaFrame Router
 *
 * Supports:
 *  - File-based routes (routes/web.php, routes/api.php, routes/ws.php)
 *  - PHP 8.3 #[Route] attribute scanning on controllers
 *  - Route groups, prefixes, middleware
 *  - Resource routes
 *  - Named routes
 *  - WebSocket routes (#[WsRoute])
 */
class Router
{
    protected RouteCollection $routes;

    /** Current group attributes stack */
    protected array $groupStack = [];

    /** Named routes index */
    protected array $namedRoutes = [];

    public function __construct(protected Application $app)
    {
        $this->routes = new RouteCollection();
    }

    // ─────────────────────────────────────────────────────────────────
    //  Route Registration
    // ─────────────────────────────────────────────────────────────────

    public function get(string $uri, array|string|callable $action): Route
    {
        return $this->addRoute(['GET', 'HEAD'], $uri, $action);
    }

    public function post(string $uri, array|string|callable $action): Route
    {
        return $this->addRoute(['POST'], $uri, $action);
    }

    public function put(string $uri, array|string|callable $action): Route
    {
        return $this->addRoute(['PUT'], $uri, $action);
    }

    public function patch(string $uri, array|string|callable $action): Route
    {
        return $this->addRoute(['PATCH'], $uri, $action);
    }

    public function delete(string $uri, array|string|callable $action): Route
    {
        return $this->addRoute(['DELETE'], $uri, $action);
    }

    public function options(string $uri, array|string|callable $action): Route
    {
        return $this->addRoute(['OPTIONS'], $uri, $action);
    }

    public function any(string $uri, array|string|callable $action): Route
    {
        return $this->addRoute(['GET','HEAD','POST','PUT','PATCH','DELETE'], $uri, $action);
    }

    public function match(array $methods, string $uri, array|string|callable $action): Route
    {
        return $this->addRoute(array_map('strtoupper', $methods), $uri, $action);
    }

    /**
     * Register a catch-all that runs only when no other route matched.
     *
     * Use this for a custom 404 page instead of a wildcard `get()` at the
     * bottom of routes/web.php. A wildcard is matched in registration order,
     * and packages register their routes later, while their providers boot —
     * so the wildcard swallows them and the package's pages 404 with nothing
     * in either file to explain why.
     *
     * The default pattern spans slashes, since a fallback that stopped at the
     * first segment would miss exactly the nested URLs it exists to catch.
     */
    public function fallback(array|string|callable $action, string $parameter = 'path'): Route
    {
        return $this->addRoute(['GET', 'HEAD'], '/{' . $parameter . '}', $action, fallback: true)
            ->where($parameter, '.*');
    }

    /**
     * Register a full-page LiveLib reactive component route.
     */
    public function livelib(string $uri, string $component): Route
    {
        return $this->get($uri, function () use ($component) {
            $compiler = $this->app->make('blade');
            $request  = $this->app->make('request');
            $route    = $request->getAttribute('_route');
            $params   = $route ? $route->getParameters() : [];
            
            // Try to find a default layout
            $layout = "layouts.app";
            
            // Pass parameters as an associative array to the directive
            $template = "@extends('$layout')\n@section('content')\n    @livelib('$component', \$params)\n@endsection";
            
            return $compiler->renderString($template, ['params' => $params]);
        });
    }

    // ─────────────────────────────────────────────────────────────────
    //  Resource Routes
    // ─────────────────────────────────────────────────────────────────

    /**
     * Register RESTful resource routes.
     *
     *  GET    /{resource}           → index
     *  GET    /{resource}/create    → create
     *  POST   /{resource}           → store
     *  GET    /{resource}/{id}      → show
     *  GET    /{resource}/{id}/edit → edit
     *  PUT    /{resource}/{id}      → update
     *  DELETE /{resource}/{id}      → destroy
     */
    public function resource(string $name, string $controller, array $options = []): void
    {
        $only   = $options['only']   ?? ['index','create','store','show','edit','update','destroy'];
        $except = $options['except'] ?? [];
        $prefix = str_replace('.', '/', $name);

        // Order matters: /{resource}/create must be registered before
        // /{resource}/{id}, otherwise "create" is swallowed as an {id}.
        $map = [
            'index'   => [['GET'],          "/$prefix",           'index'],
            'create'  => [['GET'],          "/$prefix/create",    'create'],
            'store'   => [['POST'],         "/$prefix",           'store'],
            'edit'    => [['GET'],          "/$prefix/{id}/edit", 'edit'],
            'show'    => [['GET'],          "/$prefix/{id}",      'show'],
            // Browsers can only spoof PUT via POST+_method, and API clients
            // routinely send PATCH: accepting only PUT made half the
            // conventional update requests 404.
            'update'  => [['PUT', 'PATCH'], "/$prefix/{id}",      'update'],
            'destroy' => [['DELETE'],       "/$prefix/{id}",      'destroy'],
        ];

        foreach ($map as $action => [$methods, $uri, $method_name]) {
            if (in_array($action, $only, true) && ! in_array($action, $except, true)) {
                $this->addRoute($methods, $uri, [$controller, $method_name])
                     ->name("$name.$action");
            }
        }
    }

    // ─────────────────────────────────────────────────────────────────
    //  Route Groups
    // ─────────────────────────────────────────────────────────────────

    /**
     * Attributes accumulated by the fluent builders (prefix()/middleware()/
     * name()) that are waiting for the group() call that consumes them.
     */
    protected array $pendingGroup = [];

    public function group(array $attributes, \Closure $callback): void
    {
        // Merge in anything staged by ->prefix()/->middleware()/->name().
        if ($this->pendingGroup !== []) {
            $attributes = array_merge_recursive($this->pendingGroup, $attributes);
            $this->pendingGroup = [];
        }

        $this->groupStack[] = $attributes;

        // finally: a route file that throws mid-group used to leave its
        // prefix on the stack forever, silently prefixing every route
        // registered afterwards.
        try {
            $callback($this);
        } finally {
            array_pop($this->groupStack);
        }
    }

    /**
     * Stage a prefix for the next group().
     *
     * These builders used to push straight onto $groupStack, which group()
     * never popped, so `Route::prefix('api')->group(...)` permanently
     * prefixed every subsequent route in the application with /api.
     */
    public function prefix(string $prefix): static
    {
        $this->pendingGroup['prefix'] = $prefix;
        return $this;
    }

    public function middleware(string|array $middleware): static
    {
        $this->pendingGroup['middleware'] = array_merge(
            (array) ($this->pendingGroup['middleware'] ?? []),
            (array) $middleware
        );

        return $this;
    }

    public function name(string $name): static
    {
        $this->pendingGroup['name'] = $name;
        return $this;
    }

    // ─────────────────────────────────────────────────────────────────
    //  Core Registration
    // ─────────────────────────────────────────────────────────────────

    protected function addRoute(array $methods, string $uri, mixed $action, bool $fallback = false): Route
    {
        $uri    = $this->applyGroupPrefix($uri);
        $route  = new Route($methods, $uri, $action);

        // Apply group middleware
        $middleware = $this->getGroupMiddleware();
        if ($middleware) {
            $route->middleware($middleware);
        }

        $route->setNamePrefix($this->getGroupNamePrefix());

        if ($fallback) {
            $this->routes->addFallback($route);
        } else {
            $this->routes->add($route);
        }

        return $route;
    }

    /**
     * Concatenated name prefix contributed by the enclosing group stack,
     * e.g. Route::name('admin.')->group(...) => "admin.".
     */
    protected function getGroupNamePrefix(): string
    {
        $prefix = '';

        foreach ($this->groupStack as $group) {
            if (isset($group['name']) && is_string($group['name'])) {
                $prefix .= $group['name'];
            }
        }

        return $prefix;
    }

    protected function applyGroupPrefix(string $uri): string
    {
        $prefixes = [];

        foreach ($this->groupStack as $group) {
            if (isset($group['prefix'])) {
                $prefixes[] = trim($group['prefix'], '/');
            }
        }

        $prefix = implode('/', array_filter($prefixes));
        $uri    = trim($uri, '/');

        return '/' . ltrim($prefix . '/' . $uri, '/');
    }

    protected function getGroupMiddleware(): array
    {
        $middleware = [];

        foreach ($this->groupStack as $group) {
            if (isset($group['middleware'])) {
                $middleware = array_merge($middleware, (array) $group['middleware']);
            }
        }

        return $middleware;
    }

    // ─────────────────────────────────────────────────────────────────
    //  Attribute Scanning
    // ─────────────────────────────────────────────────────────────────

    /**
     * Scan a controller class for #[Route] attributes and register them.
     */
    public function scanController(string $class): void
    {
        if (! class_exists($class)) {
            return;
        }

        $reflector       = new \ReflectionClass($class);
        $classPrefix     = '';
        $classMiddleware = [];

        // Class-level attributes. Filtering by class *before* calling
        // newInstance() matters: the old code instantiated every attribute it
        // found, so one unrelated attribute (a PHPUnit marker, #[Deprecated],
        // an attribute from another package) aborted the whole route scan
        // with "Attribute class ... not found".
        foreach ($reflector->getAttributes(\Libxa\Router\Attributes\Prefix::class) as $attr) {
            $classPrefix = '/' . ltrim($attr->newInstance()->prefix, '/');
        }

        foreach ($reflector->getAttributes(\Libxa\Router\Attributes\ApiController::class) as $attr) {
            if ($classPrefix === '') {
                $classPrefix = '/' . ltrim($attr->newInstance()->prefix, '/');
            }
        }

        foreach ($reflector->getAttributes(\Libxa\Router\Attributes\Middleware::class) as $attr) {
            $classMiddleware = array_merge($classMiddleware, (array) $attr->newInstance()->middleware);
        }

        foreach ($reflector->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->isStatic() || $method->getDeclaringClass()->getName() !== $class) {
                continue;
            }

            // Method-level middleware applies to every route on the method.
            $methodMiddleware = [];
            foreach ($method->getAttributes(\Libxa\Router\Attributes\Middleware::class) as $mAttr) {
                $methodMiddleware = array_merge($methodMiddleware, (array) $mAttr->newInstance()->middleware);
            }

            foreach ($method->getAttributes(\Libxa\Router\Attributes\Route::class) as $attr) {
                $instance = $attr->newInstance();

                $uri   = rtrim($classPrefix, '/') . '/' . ltrim($instance->uri, '/');
                $route = $this->addRoute($instance->methods, $uri, [$class, $method->getName()]);

                if ($instance->name !== '') {
                    $route->name($instance->name);
                }

                $route->middleware(array_merge($classMiddleware, $methodMiddleware));
            }
        }
    }

    /**
     * Scan a directory of controllers for #[Route] attributes.
     */
    public function scanDirectory(string $directory, string $namespace): void
    {
        if (! is_dir($directory)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $relative = str_replace($directory . DIRECTORY_SEPARATOR, '', $file->getPathname());
            $class    = $namespace . '\\' . str_replace([DIRECTORY_SEPARATOR, '.php'], ['\\', ''], $relative);

            if (class_exists($class)) {
                $this->scanController($class);
            }
        }
    }

    // ─────────────────────────────────────────────────────────────────
    //  Dispatch
    // ─────────────────────────────────────────────────────────────────

    public function dispatch(Request $request): Response
    {
        $route = $this->routes->match($request);

        if ($route === null) {
            // Distinguish "no such URL" from "wrong verb for this URL".
            // Returning 404 for both hides real bugs (a form POSTing to a
            // GET-only route looked identical to a typo in the path) and
            // violates RFC 9110, which requires 405 + Allow.
            $allowed = $this->routes->allowedMethods($request->path());

            if ($allowed !== []) {
                return new Response(
                    405,
                    ['Content-Type' => 'text/html; charset=utf-8', 'Allow' => implode(', ', $allowed)],
                    $this->render405($allowed)
                );
            }

            return new Response(404, ['Content-Type' => 'text/html; charset=utf-8'], $this->render404());
        }

        // Run through middleware pipeline
        $middlewares = $route->getMiddleware();
        $handler     = fn(Request $req) => $this->runAction($route, $req);

        $pipeline = new Pipeline($this->app);

        return $pipeline->send($request)->through($middlewares)->then($handler);
    }

    protected function runAction(Route $route, Request $request): Response
    {
        $action = $route->getAction();

        // Prefer the per-request copy: the Route object is shared for the
        // lifetime of the process, so its own parameters can be stale under
        // a persistent runtime.
        $parameters = $request->getAttribute('_route_params') ?? $route->getParameters();

        $request->setAttribute('_route', $route);
        $this->app->instance('request', $request);
        $this->app->instance(Request::class, $request);

        if ($action instanceof \Closure) {
            $result = $this->app->call($action, $parameters);
        } elseif (is_array($action)) {
            [$class, $method] = $action;

            if (! class_exists($class)) {
                throw new \RuntimeException(
                    "Controller [{$class}] for route [{$route->getUri()}] does not exist."
                );
            }

            $controller = $this->app->make($class);

            if (! method_exists($controller, $method)) {
                throw new \RuntimeException(
                    "Controller method [{$class}::{$method}()] for route [{$route->getUri()}] does not exist."
                );
            }

            $result = $this->app->call([$controller, $method], $parameters);
        } elseif (is_string($action) && str_contains($action, '@')) {
            $result = $this->app->call($action, $parameters);
        } else {
            throw new \RuntimeException(
                "Route [{$route->getUri()}] has no invokable action."
            );
        }

        return $this->toResponse($result);
    }

    protected function toResponse(mixed $result): Response
    {
        if ($result instanceof Response) {
            return $result;
        }

        if ($result === null) {
            return new Response(200, [], '');
        }

        if (is_string($result)) {
            return new Response(200, ['Content-Type' => 'text/html; charset=utf-8'], $result);
        }

        if (is_array($result)
            || $result instanceof \JsonSerializable
            || (is_object($result) && method_exists($result, 'toArray'))
        ) {
            $data = match (true) {
                is_array($result)                      => $result,
                $result instanceof \JsonSerializable    => $result,
                default                                => $result->toArray(),
            };

            // json_encode returns false on malformed UTF-8 or recursion; the
            // old code shipped that false straight into the body as "".
            $json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            if ($json === false) {
                throw new \RuntimeException(
                    'Failed to encode the controller result as JSON: ' . json_last_error_msg()
                );
            }

            return new Response(200, ['Content-Type' => 'application/json'], $json);
        }

        if (is_scalar($result)) {
            return new Response(200, ['Content-Type' => 'text/html; charset=utf-8'], (string) $result);
        }

        if ($result instanceof \Stringable) {
            return new Response(200, ['Content-Type' => 'text/html; charset=utf-8'], (string) $result);
        }

        throw new \RuntimeException(
            'A route action must return a Response, string, array or JSON-serialisable value; got '
            . get_debug_type($result) . '.'
        );
    }

    protected function render405(array $allowed): string
    {
        $list = htmlspecialchars(implode(', ', $allowed), ENT_QUOTES, 'UTF-8');

        return '<!DOCTYPE html><html><head><title>405 Method Not Allowed</title>
        <style>body{font-family:system-ui;background:#0f0f0f;color:#e0e0e0;display:flex;align-items:center;justify-content:center;height:100vh;margin:0}
        .box{text-align:center}h1{font-size:5rem;margin:0;color:#7ab8ff}p{color:#888}</style></head>
        <body><div class="box"><h1>405</h1><p>Method not allowed. Allowed: ' . $list . '</p></div></body></html>';
    }

    protected function render404(): string
    {
        return '<!DOCTYPE html><html><head><title>404 Not Found</title>
        <style>body{font-family:system-ui;background:#0f0f0f;color:#e0e0e0;display:flex;align-items:center;justify-content:center;height:100vh;margin:0}
        .box{text-align:center}h1{font-size:5rem;margin:0;color:#7ab8ff}p{color:#888}</style></head>
        <body><div class="box"><h1>404</h1><p>Page not found | LibxaFrame</p></div></body></html>';
    }

    // ─────────────────────────────────────────────────────────────────
    //  Named Routes & URL Generation
    // ─────────────────────────────────────────────────────────────────

    public function getByName(string $name): ?Route
    {
        return $this->routes->getByName($name);
    }

    /**
     * Build an absolute URL for a named route.
     */
    public function url(string $name, array $parameters = []): string
    {
        $route = $this->getByName($name);

        if ($route === null) {
            throw new \InvalidArgumentException("Route [$name] not defined.");
        }

        $uri     = $route->getUri();
        $unused  = $parameters;

        foreach ($parameters as $key => $value) {
            if (is_array($value) || is_object($value)) {
                if ($value instanceof \BackedEnum) {
                    $value = $value->value;
                } elseif (method_exists($value, 'getRouteKey')) {
                    $value = $value->getRouteKey();
                } elseif ($value instanceof \Stringable) {
                    $value = (string) $value;
                } else {
                    throw new \InvalidArgumentException(
                        "Route parameter [{$key}] for route [{$name}] must be a scalar, got "
                        . get_debug_type($value) . '.'
                    );
                }
            }

            // rawurlencode: an unescaped value (a slug with a space or '#')
            // used to silently produce a broken URL.
            $encoded = rawurlencode((string) $value);
            $before  = $uri;

            $uri = str_replace(["{{$key}}", "{{$key}?}"], $encoded, $uri);

            if ($uri !== $before) {
                unset($unused[$key]);
            }
        }

        // Any remaining required placeholder means the caller forgot an
        // argument: better a clear exception than a URL containing "{id}".
        if (preg_match('/\{(\w+)\}/', $uri, $missing)) {
            throw new \InvalidArgumentException(
                "Missing required parameter [{$missing[1]}] for route [{$name}]."
            );
        }

        // Remove optional params that weren't filled (plus their slash).
        $uri = preg_replace('#/?\{[^}]+\?\}#', '', $uri) ?? $uri;

        $base = rtrim((string) $this->app->env('APP_URL', 'http://localhost:8000'), '/');
        $url  = $base . '/' . ltrim($uri, '/');

        // Leftover parameters become a query string, matching the convention
        // every mainstream router follows.
        if ($unused !== []) {
            $url .= '?' . http_build_query($unused);
        }

        return $url;
    }

    public function getRoutes(): RouteCollection
    {
        return $this->routes;
    }

    /**
     * Populate the route collection directly from a cached, pre-resolved
     * route definition array (as produced by `route:cache`), bypassing the
     * normal file-based registration (route files, group prefixes, etc.:
     * all of that is already baked into the cached definitions).
     *
     * @param array<int, array{methods: array, uri: string, action: mixed, name?: string, middleware?: array}> $cached
     */
    public function loadCachedRoutes(array $cached): void
    {
        foreach ($cached as $definition) {
            $route = new Route($definition['methods'], $definition['uri'], $definition['action']);

            if (! empty($definition['name'])) {
                $route->name($definition['name']);
            }

            if (! empty($definition['middleware'])) {
                $route->middleware($definition['middleware']);
            }

            $this->routes->add($route);
        }
    }
}
