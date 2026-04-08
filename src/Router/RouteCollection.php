<?php

declare(strict_types=1);

namespace Libxa\Router;

use Libxa\Http\Request;

/**
 * Collection of registered routes.
 */
class RouteCollection
{
    /** @var Route[] */
    protected array $routes = [];

    /**
     * Add a route to the collection.
     */
    public function add(Route $route): void
    {
        $this->routes[] = $route;
    }

    /**
     * Match a request to a route.
     */
    public function match(Request $request): ?Route
    {
        foreach ($this->routes as $route) {
            if ($route->matches($request)) {
                return $route;
            }
        }

        return null;
    }

    /**
     * Get all registered routes.
     *
     * @return Route[]
     */
    public function all(): array
    {
        return $this->routes;
    }
}
