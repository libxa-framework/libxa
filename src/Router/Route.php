<?php

declare(strict_types=1);

namespace Libxa\Router;

use Libxa\Http\Request;

/**
 * Represents a single registered route.
 */
class Route
{
    protected string $name = '';
    protected array  $middleware = [];
    protected array  $parameters = [];
    protected ?string $pattern = null;

    public function __construct(
        protected array $methods,
        protected string $uri,
        protected mixed $action
    ) {
        $this->uri = '/' . trim($uri, '/');
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

    public function middleware(string|array $middleware): static
    {
        $this->middleware = array_merge(
            $this->middleware,
            array_filter((array) $middleware)
        );
        return $this;
    }

    // ─────────────────────────────────────────────────────────────────
    //  Matching
    // ─────────────────────────────────────────────────────────────────

    public function matches(Request $request): bool
    {
        if (! in_array($request->method(), $this->methods)) {
            return false;
        }

        $uri  = '/' . trim($request->path(), '/');
        $path = '/' . trim($this->uri, '/');

        if ($path === $uri) {
            return true;
        }

        if ($this->pattern !== null) {
            if (preg_match($this->pattern, $uri, $matches)) {
                // Extract named route parameters
                foreach ($matches as $key => $value) {
                    if (is_string($key)) {
                        $this->parameters[$key] = $value;
                    }
                }
                return true;
            }
        }

        return false;
    }

    protected function compilePattern(): void
    {
        $uri = '/' . ltrim($this->uri, '/');

        // Check if URI has any {param} segments
        if (! str_contains($uri, '{')) {
            $this->pattern = null;
            return;
        }

        // Convert {param} and {param?} to named regex groups
        $pattern = preg_replace_callback(
            '/\{(\w+)(\?)?\}/',
            function (array $m): string {
                $name     = $m[1];
                $optional = isset($m[2]) && $m[2] === '?';

                return $optional
                    ? "(?P<{$name}>[^/]+)?"
                    : "(?P<{$name}>[^/]+)";
            },
            $uri
        );

        $this->pattern = "#^{$pattern}$#";
    }

    // ─────────────────────────────────────────────────────────────────
    //  Getters
    // ─────────────────────────────────────────────────────────────────

    public function getMethods(): array    { return $this->methods; }
    public function getUri(): string       { return $this->uri; }
    public function getAction(): mixed     { return $this->action; }
    public function getName(): string      { return $this->name; }
    public function getMiddleware(): array { return $this->middleware; }
    public function getParameters(): array { return $this->parameters; }
}
