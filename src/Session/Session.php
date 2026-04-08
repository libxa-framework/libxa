<?php

declare(strict_types=1);

namespace Libxa\Session;

/**
 * Session Manager
 *
 * Wraps native PHP sessions with a cleaner API, supporting
 * flash data, old input, and CSRF tokens.
 */
class Session
{
    protected bool $started = false;

    public function __construct()
    {
        $this->start();
    }

    /**
     * Start the session if not already started.
     */
    public function start(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $this->started = true;
    }

    /**
     * Get a value from the session.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    /**
     * Put a value into the session.
     */
    public function put(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    /**
     * Remove a value from the session.
     */
    public function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    /**
     * Flush all session data.
     */
    public function flush(): void
    {
        $_SESSION = [];
    }

    /**
     * Flash a value for the next request only.
     */
    public function flash(string $key, mixed $value): void
    {
        $_SESSION['_flash']['next'][$key] = $value;
    }

    /**
     * Retrieve and clear flash data (usually called by middleware).
     */
    public function ageFlashData(): void
    {
        $_SESSION['_flash']['old'] = $_SESSION['_flash']['next'] ?? [];
        $_SESSION['_flash']['next'] = [];
    }

    /**
     * Get CSRF token or generate if missing.
     */
    public function token(): string
    {
        if (! isset($_SESSION['_token'])) {
            $_SESSION['_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_token'];
    }

    /**
     * Regenerate the session ID.
     */
    public function regenerate(bool $destroy = false): bool
    {
        return session_regenerate_id($destroy);
    }
}
