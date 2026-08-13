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
     * Routes considered only after every ordinary route has been tried.
     *
     * Kept in their own list rather than at the end of $routes because
     * "registered last" and "matched last" are not the same thing: a package's
     * routes are registered while its provider boots, which happens after the
     * application's own route file has run. A catch-all written at the bottom
     * of routes/web.php would therefore still shadow every route a package
     * adds, and the package's pages 404 for a reason nothing in either file
     * makes visible.
     *
     * @var Route[]
     */
    protected array $fallbacks = [];

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
     * Add a route that only matches once nothing else has.
     */
    public function addFallback(Route $route): void
    {
        $this->fallbacks[]    = $route;
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

        // Ordinary routes first, in registration order; fallbacks only once
        // none of them matched.
        foreach ([$this->routes, $this->fallbacks] as $candidates) {
            foreach ($candidates as $route) {
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

        foreach ($this->all() as $route) {
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

        foreach ($this->all() as $route) {
            $routeName = $route->getName();

            if ($routeName !== '') {
                $this->named[$routeName] = $route;
            }
        }

        $this->nameIndexStale = false;
    }

    /**
     * Get all registered routes, in the order they are matched.
     *
     * @return Route[]
     */
    public function all(): array
    {
        return array_merge($this->routes, $this->fallbacks);
    }

    /**
     * Get only the fallback routes.
     *
     * @return Route[]
     */
    public function fallbacks(): array
    {
        return $this->fallbacks;
    }

    public function count(): int
    {
        return count($this->routes) + count($this->fallbacks);
    }
}
