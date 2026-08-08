<?php

declare(strict_types=1);

namespace Libxa\Router;

use Libxa\Http\Request;

/**
 * Represents a single registered route.
 *
 * Stability notes:
 *  - matches() no longer stores the extracted parameters on the Route as its
 *    only copy. A Route object lives for the whole process, so under a
 *    persistent runtime (Workerman, the WS server, queue workers) request N+1
 *    could read the parameters left behind by request N. Parameters are now
 *    returned from match() and stashed on the Request, which is per-request.
 *  - Optional segments ({id?}) used to compile to "/users/(?P<id>[^/]+)?",
 *    which cannot match "/users" because the slash was mandatory. The slash
 *    is now part of the optional group.
 *  - Literal parts of the URI are escaped, so a route like "/pricing.html"
 *    no longer matches "/pricingXhtml".
 */
class Route
{
    protected string $name = '';
    protected string $namePrefix = '';
    protected array  $middleware = [];
    protected array  $parameters = [];
    protected ?string $pattern = null;

    /** Per-parameter regex constraints, e.g. ['id' => '\d+'] */
    protected array $constraints = [];

    public function __construct(
        protected array $methods,
        protected string $uri,
        protected mixed $action
    ) {
        $this->methods = array_map('strtoupper', $methods);
        $this->uri     = '/' . trim($uri, '/');
        $this->compilePattern();
    }

    // ─────────────────────────────────────────────────────────────────
    //  Fluent setters
    // ─────────────────────────────────────────────────────────────────

    public function name(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    /**
     * Applied by the router from the enclosing group stack.
     */
    public function setNamePrefix(string $prefix): static
    {
        $this->namePrefix = $prefix;
        return $this;
    }

    /**
     * Attach middleware to this route.
     *
     * Accepts a class name, an alias, a group name, an already-constructed
     * middleware object, or a closure. The signature used to be
     * `string|array`, so passing a closure — which the pipeline happily
     * executes — raised a TypeError here first. array_unique() also had to
     * go: it stringifies its input, which is a fatal for a Closure.
     */
    public function middleware(string|array|object $middleware): static
    {
        $incoming = is_array($middleware) ? $middleware : [$middleware];

        foreach ($incoming as $pipe) {
            if ($pipe === null || $pipe === '' || $pipe === []) {
                continue;
            }

            // De-duplicate string pipes only; two distinct closures are two
            // distinct middleware even if they look alike.
            if (is_string($pipe) && in_array($pipe, $this->middleware, true)) {
                continue;
            }

            $this->middleware[] = $pipe;
        }

        return $this;
    }

    /**
     * Replace this route's middleware stack outright.
     */
    public function withoutMiddleware(): static
    {
        $this->middleware = [];

        return $this;
    }

    /**
     * Constrain one or more route parameters with a regex.
     *
     *   $route->where('id', '\d+')
     *   $route->where(['id' => '\d+', 'slug' => '[a-z-]+'])
     */
    public function where(string|array $name, ?string $pattern = null): static
    {
        foreach (is_array($name) ? $name : [$name => $pattern] as $key => $regex) {
            if (is_string($regex) && $regex !== '') {
                $this->constraints[$key] = $regex;
            }
        }

        $this->compilePattern();

        return $this;
    }

    // ─────────────────────────────────────────────────────────────────
    //  Matching
    // ─────────────────────────────────────────────────────────────────

    /**
     * Whether this route's URI pattern matches the request path,
     * ignoring the HTTP method (used to detect 405 vs 404).
     */
    public function matchesPath(string $path): bool
    {
        return $this->extractParameters($path) !== null;
    }

    public function matchesMethod(string $method): bool
    {
        return in_array(strtoupper($method), $this->methods, true);
    }

    public function matches(Request $request): bool
    {
        if (! $this->matchesMethod($request->method())) {
            return false;
        }

        $parameters = $this->extractParameters($request->path());

        if ($parameters === null) {
            return false;
        }

        $this->parameters = $parameters;

        return true;
    }

    /**
     * Extract the route parameters for a path, or null when it doesn't match.
     *
     * @return array<string, string>|null
     */
    public function extractParameters(string $path): ?array
    {
        $uri = '/' . trim($path, '/');

        if ($this->pattern === null) {
            return $uri === $this->uri ? [] : null;
        }

        if (! preg_match($this->pattern, $uri, $matches)) {
            return null;
        }

        $parameters = [];

        foreach ($matches as $key => $value) {
            // An unmatched optional group yields '' — treat it as absent so
            // the controller's default parameter value takes over.
            if (is_string($key) && $value !== '') {
                $parameters[$key] = $value;
            }
        }

        return $parameters;
    }

    protected function compilePattern(): void
    {
        $uri = '/' . ltrim($this->uri, '/');

        if (! str_contains($uri, '{')) {
            $this->pattern = null;
            return;
        }

        $pattern = '';
        $offset  = 0;

        // Walk the placeholders so the literal text between them can be
        // preg_quote()'d rather than injected into the regex verbatim.
        if (preg_match_all('/(\/?)\{(\w+)(\?)?\}/', $uri, $all, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {
            foreach ($all as $m) {
                [$whole, $wholeOffset] = $m[0];
                $slash                 = $m[1][0];
                $name                  = $m[2][0];
                $optional              = ($m[3][0] ?? '') === '?';

                $pattern .= preg_quote(substr($uri, $offset, $wholeOffset - $offset), '#');
                $offset   = $wholeOffset + strlen($whole);

                $segment = $this->constraints[$name] ?? '[^/]+';

                $pattern .= $optional
                    // The leading slash belongs *inside* the optional group,
                    // otherwise "/users/{id?}" can never match "/users".
                    ? '(?:' . preg_quote($slash, '#') . "(?P<{$name}>{$segment}))?"
                    : preg_quote($slash, '#') . "(?P<{$name}>{$segment})";
            }
        }

        $pattern .= preg_quote(substr($uri, $offset), '#');

        $this->pattern = "#^{$pattern}$#";
    }

    // ─────────────────────────────────────────────────────────────────
    //  Getters
    // ─────────────────────────────────────────────────────────────────

    public function getMethods(): array    { return $this->methods; }
    public function getUri(): string       { return $this->uri; }
    public function getAction(): mixed     { return $this->action; }
    public function getMiddleware(): array { return $this->middleware; }
    public function getParameters(): array { return $this->parameters; }

    public function getName(): string
    {
        return $this->name === '' ? '' : $this->namePrefix . $this->name;
    }
}
