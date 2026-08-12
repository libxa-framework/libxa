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

    /** @var array<string, Route> name => route */
    protected array $named = [];

    /** Whether $named needs rebuilding before the next lookup. */
    protected bool $nameIndexStale = true;

    /**
     * Add a route to the collection.
     */
    public function add(Route $route): void
    {
        $this->routes[]       = $route;
        $this->nameIndexStale = true;
    }

    /**
     * Match a request to a route.
     *
     * The matched parameters are written onto the Request (not just onto the
     * shared Route object) so they cannot leak between requests in a
     * long-running worker process.
     */
    public function match(Request $request): ?Route
    {
        $path = $request->path();

        foreach ($this->routes as $route) {
            if (! $route->matchesMethod($request->method())) {
                continue;
            }

            $parameters = $route->extractParameters($path);

            if ($parameters === null) {
                continue;
            }

            $route->matches($request); // keeps Route::getParameters() in sync
            $request->setAttribute('_route_params', $parameters);

            return $route;
        }

        return null;
    }

    /**
     * HTTP methods accepted at a path by routes whose URI matches but whose
     * verb does not. Empty when the path itself is unknown: that is a 404,
     * whereas a non-empty list means the correct answer is 405 plus an
     * Allow header, which the router previously reported as a 404.
     *
     * @return string[]
     */
    public function allowedMethods(string $path): array
    {
        $allowed = [];

        foreach ($this->routes as $route) {
            if ($route->matchesPath($path)) {
                $allowed = array_merge($allowed, $route->getMethods());
            }
        }

        return array_values(array_unique($allowed));
    }

    /**
     * Look a route up by name (indexed, so URL generation is not O(routes)).
     */
    public function getByName(string $name): ?Route
    {
        // ->name() is called *after* add() (fluent style), so the index is
        // always built lazily on first lookup rather than at registration.
        if ($this->nameIndexStale) {
            $this->rebuildNameIndex();
        }

        if (isset($this->named[$name])) {
            return $this->named[$name];
        }

        // ->name() is fluent and therefore runs *after* add(), so a route may
        // have been named since the index was built. One rebuild on miss keeps
        // lookups correct without giving up the O(1) hit path.
        $this->rebuildNameIndex();

        return $this->named[$name] ?? null;
    }

    protected function rebuildNameIndex(): void
    {
        $this->named = [];

        foreach ($this->routes as $route) {
            $routeName = $route->getName();

            if ($routeName !== '') {
                $this->named[$routeName] = $route;
            }
        }

        $this->nameIndexStale = false;
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

    public function count(): int
    {
        return count($this->routes);
    }
}
