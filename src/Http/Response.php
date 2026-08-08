<?php

declare(strict_types=1);

namespace Libxa\Http;

/**
 * HTTP Response
 */
class Response
{
    /** Queued Set-Cookie header values, keyed by cookie name. */
    protected array $cookies = [];

    public function __construct(
        protected int    $status  = 200,
        protected array  $headers = [],
        protected string $content = '',
    ) {}

    // ─────────────────────────────────────────────────────────────────
    //  Factories
    // ─────────────────────────────────────────────────────────────────

    public static function make(string $content = '', int $status = 200, array $headers = []): static
    {
        return new static($status, $headers, $content);
    }

    public static function view(string $view, array $data = [], int $status = 200): static
    {
        $blade   = \Libxa\Foundation\Application::getInstance()->make('blade');
        $content = $blade->render($view, $data);
        return new static($status, ['Content-Type' => 'text/html; charset=utf-8'], $content);
    }

    public static function redirect(string $url, int $status = 302): static
    {
        return new static($status, ['Location' => $url], '');
    }

    /**
     * Redirect back to the referring page.
     *
     * The Referer header is supplied by the client, so echoing it straight
     * into a Location header turned every "back" redirect into an open
     * redirect (a phishing link could bounce users off your domain through
     * your own error handler). Only same-origin referers are honoured.
     */
    public static function back(string $fallback = '/'): static
    {
        return static::redirect(static::safeReferer($_SERVER['HTTP_REFERER'] ?? null, $fallback));
    }

    /**
     * Reduce a client-supplied referer to a safe, same-origin target.
     */
    public static function safeReferer(?string $referer, string $fallback = '/'): string
    {
        if ($referer === null || trim($referer) === '') {
            return $fallback;
        }

        $parts = parse_url($referer);

        if ($parts === false) {
            return $fallback;
        }

        // A relative URL ("/dashboard") has no host and is always same-origin.
        if (! isset($parts['host'])) {
            $path = $parts['path'] ?? '/';

            // "//evil.com/x" parses as a path but browsers read it as a host.
            if (str_starts_with($path, '//')) {
                return $fallback;
            }

            return $path
                . (isset($parts['query']) ? '?' . $parts['query'] : '')
                . (isset($parts['fragment']) ? '#' . $parts['fragment'] : '');
        }

        $host = $_SERVER['HTTP_HOST'] ?? '';

        return strcasecmp($parts['host'], (string) preg_replace('/:\d+$/', '', $host)) === 0
            ? $referer
            : $fallback;
    }

    public function intended(string $default = '/'): static
    {
        $url = app('session')?->get('url.intended', $default) ?? $default;
        return $this->withStatus(302)->withHeader('Location', $url);
    }

    public static function download(string $filePath, string $name = ''): static
    {
        if (! file_exists($filePath)) {
            return new static(404, [], 'File not found');
        }

        $name    = $name ?: basename($filePath);
        $content = file_get_contents($filePath);

        return new static(200, [
            'Content-Type'        => mime_content_type($filePath) ?: 'application/octet-stream',
            'Content-Disposition' => "attachment; filename=\"$name\"",
            'Content-Length'      => (string) strlen($content),
        ], $content);
    }

    // ─────────────────────────────────────────────────────────────────
    //  Fluent setters
    // ─────────────────────────────────────────────────────────────────

    public function withStatus(int $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function withHeader(string $name, string $value): static
    {
        $this->headers[$name] = $value;
        return $this;
    }

    public function withHeaders(array $headers): static
    {
        $this->headers = array_merge($this->headers, $headers);
        return $this;
    }

    public function withContent(string $content): static
    {
        $this->content = $content;
        return $this;
    }

    /**
     * Queue a cookie on the response.
     *
     * Cookies are kept in their own list rather than in $headers: writing them
     * to $headers['Set-Cookie'] meant each call overwrote the previous one, so
     * a response could only ever carry a single cookie.
     */
    public function cookie(
        string $name,
        string $value,
        int    $minutes  = 0,
        string $path     = '/',
        string $domain   = '',
        bool   $secure   = false,
        bool   $httpOnly = true,
        string $sameSite = 'Lax',
    ): static {
        $expires = $minutes ? time() + ($minutes * 60) : 0;
        $parts   = [
            urlencode($name) . '=' . urlencode($value),
            "Path=$path",
            $expires ? 'Expires=' . gmdate('D, d M Y H:i:s T', $expires) : '',
            $expires ? 'Max-Age=' . ($minutes * 60) : '',
            $domain  ? "Domain=$domain" : '',
            $secure  ? 'Secure' : '',
            $httpOnly ? 'HttpOnly' : '',
            'SameSite=' . $sameSite,
        ];

        $this->cookies[$name] = implode('; ', array_filter($parts));

        return $this;
    }

    /**
     * Instruct the browser to delete a cookie.
     */
    public function forgetCookie(string $name, string $path = '/', string $domain = ''): static
    {
        $this->cookies[$name] = implode('; ', array_filter([
            urlencode($name) . '=deleted',
            "Path=$path",
            'Expires=' . gmdate('D, d M Y H:i:s T', 0),
            'Max-Age=0',
            $domain ? "Domain=$domain" : '',
            'HttpOnly',
            'SameSite=Lax',
        ]));

        return $this;
    }

    /**
     * The queued Set-Cookie header values.
     *
     * @return array<string, string>
     */
    public function getCookies(): array
    {
        return $this->cookies;
    }

    // ─────────────────────────────────────────────────────────────────
    //  Send
    // ─────────────────────────────────────────────────────────────────

    public function send(): void
    {
        if (! headers_sent()) {
            http_response_code($this->status);

            foreach ($this->headers as $name => $value) {
                $replace = true;
                foreach ((array) $value as $single) {
                    header("$name: $single", $replace);
                    $replace = false; // subsequent values append
                }
            }

            // replace:false so several cookies survive in one response.
            foreach ($this->cookies as $cookie) {
                header('Set-Cookie: ' . $cookie, replace: false);
            }
        }

        // 204/304 and HEAD responses must not carry a body.
        if ($this->status === 204 || $this->status === 304) {
            return;
        }

        if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'HEAD') {
            return;
        }

        echo $this->content;
    }

    // ─────────────────────────────────────────────────────────────────
    //  Getters
    // ─────────────────────────────────────────────────────────────────

    public function json(mixed $data = [], int $status = 200, array $headers = []): JsonResponse
    {
        return new JsonResponse($data, $status, $headers);
    }

    public function getStatus(): int    { return $this->status; }
    public function getHeaders(): array { return $this->headers; }
    public function getContent(): string { return $this->content; }
    public function getHeader(string $name): ?string { return $this->headers[$name] ?? null; }

    /**
     * Flash data to the session.
     */
    public function with(string $key, mixed $value): static
    {
        app('session')->flash($key, $value);
        return $this;
    }
}

