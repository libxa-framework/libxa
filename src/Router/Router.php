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

        $map = [
            'index'   => ['GET',    "/$prefix",              'index'],
            'create'  => ['GET',    "/$prefix/create",       'create'],
            'store'   => ['POST',   "/$prefix",              'store'],
            'show'    => ['GET',    "/$prefix/{id}",         'show'],
            'edit'    => ['GET',    "/$prefix/{id}/edit",    'edit'],
            'update'  => ['PUT',    "/$prefix/{id}",         'update'],
            'destroy' => ['DELETE', "/$prefix/{id}",         'destroy'],
        ];

        foreach ($map as $action => [$method, $uri, $method_name]) {
            if (in_array($action, $only) && ! in_array($action, $except)) {
                $this->addRoute([$method], $uri, [$controller, $method_name])
                     ->name("$name.$action");
            }
        }
    }

    // ─────────────────────────────────────────────────────────────────
    //  Route Groups
    // ─────────────────────────────────────────────────────────────────

    public function group(array $attributes, \Closure $callback): void
    {
        $this->groupStack[] = $attributes;
        $callback($this);
        array_pop($this->groupStack);
    }

    public function prefix(string $prefix): static
    {
        $this->groupStack[] = ['prefix' => $prefix];
        return $this;
    }

    public function middleware(string|array $middleware): static
    {
        $last = array_pop($this->groupStack) ?? [];
        $existing = (array) ($last['middleware'] ?? []);
        $last['middleware'] = array_merge($existing, (array) $middleware);
        $this->groupStack[] = $last;
        return $this;
    }

    public function name(string $name): static
    {
        $last          = array_pop($this->groupStack) ?? [];
        $last['name']  = $name;
        $this->groupStack[] = $last;
        return $this;
    }

    // ─────────────────────────────────────────────────────────────────
    //  Core Registration
    // ─────────────────────────────────────────────────────────────────

    protected function addRoute(array $methods, string $uri, mixed $action): Route
    {
        $uri    = $this->applyGroupPrefix($uri);
        $route  = new Route($methods, $uri, $action);

        // Apply group middleware
        $middleware = $this->getGroupMiddleware();
        if ($middleware) {
            $route->middleware($middleware);
        }

        $this->routes->add($route);

        return $route;
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

        $reflector    = new \ReflectionClass($class);
        $classPrefix  = '';
        $classMiddleware = [];

        // Class-level attributes
        foreach ($reflector->getAttributes() as $attr) {
            $instance = $attr->newInstance();

            if ($instance instanceof \Libxa\Router\Attributes\Prefix) {
                $classPrefix = '/' . ltrim($instance->prefix, '/');
            }
            if ($instance instanceof \Libxa\Router\Attributes\Middleware) {
                $classMiddleware = array_merge($classMiddleware, (array) $instance->middleware);
            }
        }

        foreach ($reflector->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            foreach ($method->getAttributes() as $attr) {
                $instance = $attr->newInstance();

                if ($instance instanceof \Libxa\Router\Attributes\Route) {
                    $uri    = $classPrefix . '/' . ltrim($instance->uri, '/');
                    $methods = $instance->methods;
                    $route   = $this->addRoute($methods, $uri, [$class, $method->getName()]);

                    if ($instance->name) {
                        $route->name($instance->name);
                    }

                    // Method-level middleware
                    foreach ($method->getAttributes(\Libxa\Router\Attributes\Middleware::class) as $mAttr) {
                        $mInstance = $mAttr->newInstance();
                        $route->middleware($mInstance->middleware);
                    }

                    $route->middleware($classMiddleware);
                }
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
            return new Response(404, ['Content-Type' => 'text/html'], $this->render404());
        }

        // Run through middleware pipeline
        $middlewares = $route->getMiddleware();
        $handler     = fn(Request $req) => $this->runAction($route, $req);

        $pipeline = new Pipeline($this->app);

        return $pipeline->send($request)->through($middlewares)->then($handler);
    }

    protected function runAction(Route $route, Request $request): Response
    {
        $action     = $route->getAction();
        $parameters = $route->getParameters();

        $this->app->instance('request', $request);

        if ($action instanceof \Closure) {
            $result = $this->app->call($action, $parameters);
        } elseif (is_array($action)) {
            [$class, $method] = $action;
            $controller = $this->app->make($class);
            $result     = $this->app->call([$controller, $method], $parameters);
        } else {
            $result = null;
        }

        return $this->toResponse($result);
    }

    protected function toResponse(mixed $result): Response
    {
        if ($result instanceof Response) {
            return $result;
        }

        if (is_array($result) || (is_object($result) && method_exists($result, 'toArray'))) {
            $data = is_array($result) ? $result : $result->toArray();
            return new Response(200, ['Content-Type' => 'application/json'], json_encode($data));
        }

        if (is_string($result)) {
            return new Response(200, ['Content-Type' => 'text/html; charset=utf-8'], $result);
        }

        return new Response(200, [], '');
    }

    protected function render404(): string
    {
        return '<!DOCTYPE html><html><head><title>404 — Not Found</title>
        <style>body{font-family:system-ui;background:#0f0f0f;color:#e0e0e0;display:flex;align-items:center;justify-content:center;height:100vh;margin:0}
        .box{text-align:center}h1{font-size:5rem;margin:0;color:#7ab8ff}p{color:#888}</style></head>
        <body><div class="box"><h1>404</h1><p>Page not found — LibxaFrame</p></div></body></html>';
    }

    // ─────────────────────────────────────────────────────────────────
    //  Named Routes & URL Generation
    // ─────────────────────────────────────────────────────────────────

    public function getByName(string $name): ?Route
    {
        foreach ($this->routes->all() as $route) {
            if ($route->getName() === $name) {
                return $route;
            }
        }
        return null;
    }

    public function url(string $name, array $parameters = []): string
    {
        $route = $this->getByName($name);

        if ($route === null) {
            throw new \InvalidArgumentException("Route [$name] not defined.");
        }

        $uri = $route->getUri();

        foreach ($parameters as $key => $value) {
            $uri = str_replace("{{$key}}", $value, $uri);
            $uri = str_replace("{{$key}?}", $value, $uri);
        }

        // Remove optional params that weren't filled
        $uri = preg_replace('/\{[^}]+\?\}/', '', $uri);

        $base = rtrim($this->app->env('APP_URL', 'http://localhost:8000'), '/');

        return $base . '/' . ltrim($uri, '/');
    }

    public function getRoutes(): RouteCollection
    {
        return $this->routes;
    }
}
