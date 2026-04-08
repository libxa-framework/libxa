<?php

declare(strict_types=1);

namespace Libxa\Http;

/**
 * HTTP Response
 */
class Response
{
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

    public static function back(): static
    {
        $referer = $_SERVER['HTTP_REFERER'] ?? '/';
        return static::redirect($referer);
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

    public function cookie(
        string $name,
        string $value,
        int    $minutes  = 0,
        string $path     = '/',
        string $domain   = '',
        bool   $secure   = false,
        bool   $httpOnly = true,
    ): static {
        $expires = $minutes ? time() + ($minutes * 60) : 0;
        $parts   = [
            urlencode($name) . '=' . urlencode($value),
            "Path=$path",
            $expires ? 'Expires=' . gmdate('D, d M Y H:i:s T', $expires) : '',
            $domain  ? "Domain=$domain" : '',
            $secure  ? 'Secure' : '',
            $httpOnly ? 'HttpOnly' : '',
            'SameSite=Lax',
        ];

        $this->headers['Set-Cookie'] = implode('; ', array_filter($parts));

        return $this;
    }

    // ─────────────────────────────────────────────────────────────────
    //  Send
    // ─────────────────────────────────────────────────────────────────

    public function send(): void
    {
        if (! headers_sent()) {
            http_response_code($this->status);

            foreach ($this->headers as $name => $value) {
                header("$name: $value");
            }
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

