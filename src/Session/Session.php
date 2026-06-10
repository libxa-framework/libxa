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

    // ─────────────────────────────────────────────────────────────────
    //  Lifecycle
    // ─────────────────────────────────────────────────────────────────

    /**
     * Start the session if not already started.
     */
    public function start(): void
    {
        // Sessions are meaningless in CLI context and cause
        // "headers already sent" warnings from banner output.
        if (PHP_SAPI === 'cli') {
            $this->started = false;
            return;
        }

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $this->started = true;
    }

    public function isStarted(): bool
    {
        return $this->started;
    }

    // ─────────────────────────────────────────────────────────────────
    //  Read / Write
    // ─────────────────────────────────────────────────────────────────

    public function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public function put(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    /**
     * Retrieve and delete an item from the session.
     */
    public function pull(string $key, mixed $default = null): mixed
    {
        $value = $this->get($key, $default);
        $this->forget($key);
        return $value;
    }

    public function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

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
     * Completely destroy the session (used on logout).
     */
    public function invalidate(): void
    {
        $this->flush();
        session_destroy();
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $this->started = true;
    }

    // ─────────────────────────────────────────────────────────────────
    //  Flash data
    // ─────────────────────────────────────────────────────────────────

    public function flash(string $key, mixed $value): void
    {
        $_SESSION['_flash']['next'][$key] = $value;
    }

    /**
     * Retrieve and clear flash data (usually called by middleware on every request).
     */
    public function ageFlashData(): void
    {
        $_SESSION['_flash']['old']  = $_SESSION['_flash']['next'] ?? [];
        $_SESSION['_flash']['next'] = [];
    }

    public function getFlash(string $key, mixed $default = null): mixed
    {
        return $_SESSION['_flash']['old'][$key] ?? $default;
    }

    // ─────────────────────────────────────────────────────────────────
    //  CSRF
    // ─────────────────────────────────────────────────────────────────

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
     * Regenerate the CSRF token (e.g. after login).
     */
    public function regenerateToken(): void
    {
        $_SESSION['_token'] = bin2hex(random_bytes(32));
    }

    // ─────────────────────────────────────────────────────────────────
    //  Session ID
    // ─────────────────────────────────────────────────────────────────

    /**
     * Regenerate the session ID.
     */
    public function regenerate(bool $destroy = false): bool
    {
        return session_regenerate_id($destroy);
    }

    public function getId(): string
    {
        return session_id() ?: '';
    }

    public function setId(string $id): void
    {
        session_id($id);
    }
}
