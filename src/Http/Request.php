<?php

declare(strict_types=1);

namespace Libxa\Http;

/**
 * LibxaFrame HTTP Request
 *
 * Wraps PHP's superglobals into a clean, testable object.
 */
class Request
{
    protected array  $attributes = [];
    protected string $rawBody    = '';

    public function __construct(
        protected string $method,
        protected string $uri,
        protected array  $headers  = [],
        protected array  $query    = [],
        protected array  $post     = [],
        protected array  $files    = [],
        protected array  $server   = [],
        protected array  $cookies  = [],
        string           $body     = '',
    ) {
        $this->rawBody = $body;
    }

    /**
     * Create a Request from PHP's global state.
     */
    public static function capture(): static
    {
        $method  = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $uri     = $_SERVER['REQUEST_URI'] ?? '/';
        $headers = [];

        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $name = str_replace('_', '-', substr($key, 5));
                $headers[$name] = $value;
            } elseif (in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH'])) {
                $name = str_replace('_', '-', $key);
                $headers[$name] = $value;
            }
        }

        $body = file_get_contents('php://input') ?: '';

        $post = $_POST;

        // Handle JSON body
        if (isset($headers['CONTENT-TYPE']) &&
            str_contains($headers['CONTENT-TYPE'], 'application/json') &&
            $body !== '') {
            $post = json_decode($body, true) ?? [];
        }

        // Handle method spoofing
        $spoofed = $_POST['_method'] ?? $post['_method'] ?? $_GET['_method'] ?? null;
        if ($spoofed && in_array(strtoupper($spoofed), ['PUT', 'PATCH', 'DELETE'])) {
            $method = strtoupper($spoofed);
        }

        return new static(
            method:  $method,
            uri:     $uri,
            headers: $headers,
            query:   $_GET,
            post:    $post,
            files:   $_FILES,
            server:  $_SERVER,
            cookies: $_COOKIE,
            body:    $body,
        );
    }

    // ─────────────────────────────────────────────────────────────────
    //  Accessors
    // ─────────────────────────────────────────────────────────────────

    public function method(): string
    {
        return strtoupper($this->method);
    }

    public function isMethod(string $method): bool
    {
        return $this->method() === strtoupper($method);
    }

    public function uri(): string { return $this->uri; }

    public function path(): string
    {
        $path = parse_url($this->uri, PHP_URL_PATH) ?? '/';
        $path = '/' . ltrim($path, '/');

        // If the path starts with /index.php, we remove it to get the logical route path.
        // This allows routes to work both with and without index.php in the URL.
        if (str_starts_with($path, '/index.php/')) {
            $path = substr($path, 10);
        } elseif ($path === '/index.php') {
            $path = '/';
        }

        return '/' . ltrim($path, '/');
    }

    public function url(): string
    {
        $scheme = ($this->server['HTTPS'] ?? '') === 'on' ? 'https' : 'http';
        $host   = $this->server['HTTP_HOST'] ?? 'localhost';
        return "$scheme://$host{$this->path()}";
    }

    public function fullUrl(): string
    {
        $scheme = ($this->server['HTTPS'] ?? '') === 'on' ? 'https' : 'http';
        $host   = $this->server['HTTP_HOST'] ?? 'localhost';
        return "$scheme://$host{$this->uri}";
    }

    // ─────────────────────────────────────────────────────────────────
    //  Input
    // ─────────────────────────────────────────────────────────────────

    public function all(): array
    {
        return array_merge($this->query, $this->post);
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->post[$key] ?? $this->query[$key] ?? $this->attributes[$key] ?? $default;
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    public function only(array $keys): array
    {
        return array_intersect_key($this->all(), array_flip($keys));
    }

    public function except(array $keys): array
    {
        return array_diff_key($this->all(), array_flip($keys));
    }

    public function has(string $key): bool
    {
        $all = $this->all();
        return isset($all[$key]) && $all[$key] !== '';
    }

    public function missing(string $key): bool
    {
        return ! $this->has($key);
    }

    public function filled(string $key): bool
    {
        $value = $this->input($key);
        return $value !== null && $value !== '' && $value !== [];
    }

    public function boolean(string $key, bool $default = false): bool
    {
        $value = $this->input($key);

        if ($value === null) return $default;

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $default;
    }

    public function integer(string $key, int $default = 0): int
    {
        return (int) ($this->input($key) ?? $default);
    }

    public function string(string $key, string $default = ''): string
    {
        return (string) ($this->input($key) ?? $default);
    }

    public function rawBody(): string { return $this->rawBody; }

    public function json(?string $key = null, mixed $default = null): mixed
    {
        $data = json_decode($this->rawBody, true) ?? [];
        return $key ? ($data[$key] ?? $default) : $data;
    }

    // ─────────────────────────────────────────────────────────────────
    //  Headers, Cookies, Files
    // ─────────────────────────────────────────────────────────────────

    public function header(string $key, string $default = ''): string
    {
        $normalized = strtoupper(str_replace('-', '_', $key));
        return $this->headers[$normalized] ?? $this->headers[$key] ?? $default;
    }

    public function bearerToken(): ?string
    {
        $auth = $this->header('AUTHORIZATION');
        if (str_starts_with($auth, 'Bearer ')) {
            return substr($auth, 7);
        }
        return null;
    }

    public function cookie(string $key, mixed $default = null): mixed
    {
        return $this->cookies[$key] ?? $default;
    }

    public function file(string $key): ?UploadedFile
    {
        $file = $this->files[$key] ?? null;

        if (! $file || ! isset($file['tmp_name']) || $file['tmp_name'] === '') {
            return null;
        }

        return new UploadedFile(
            $file['name'],
            $file['type'],
            $file['tmp_name'],
            $file['error'],
            $file['size']
        );
    }

    public function hasFile(string $key): bool
    {
        return isset($this->files[$key]) && $this->files[$key]['error'] === UPLOAD_ERR_OK;
    }

    // ─────────────────────────────────────────────────────────────────
    //  Request type checks
    // ─────────────────────────────────────────────────────────────────

    public function isAjax(): bool
    {
        return $this->header('X_REQUESTED_WITH') === 'XMLHttpRequest';
    }

    public function expectsJson(): bool
    {
        return str_contains($this->header('ACCEPT', ''), 'application/json');
    }

    public function isJson(): bool
    {
        return str_contains($this->header('CONTENT-TYPE', ''), 'application/json');
    }

    public function isSecure(): bool
    {
        return ($this->server['HTTPS'] ?? '') === 'on';
    }

    // ─────────────────────────────────────────────────────────────────
    //  Attributes (set by router / middleware)
    // ─────────────────────────────────────────────────────────────────

    public function setAttribute(string $key, mixed $value): void
    {
        $this->attributes[$key] = $value;
    }

    public function getAttribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    public function ip(): string
    {
        return $this->server['HTTP_X_FORWARDED_FOR']
            ?? $this->server['REMOTE_ADDR']
            ?? '127.0.0.1';
    }

    // ─────────────────────────────────────────────────────────────────
    //  Validation shortcut
    // ─────────────────────────────────────────────────────────────────

    public function validate(array $rules): array
    {
        $validator = new \Libxa\Validation\Validator($this->all(), $rules);

        if ($validator->fails()) {
            throw new \Libxa\Validation\ValidationException($validator->errors());
        }

        return $validator->validated();
    }

    /**
     * Get the session instance.
     */
    public function session(): \Libxa\Session\Session
    {
        return app('session');
    }
}
