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

    /**
     * @param array $config config/session.php, used to configure the cookie.
     */
    public function __construct(protected array $config = [])
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

            // Give the rest of the framework a usable $_SESSION array so
            // console commands and tests can read/write session state without
            // every call site having to null-check.
            $_SESSION ??= [];

            return;
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            $this->started = true;
            return;
        }

        if (session_status() === PHP_SESSION_NONE) {
            $this->applyCookieParams();
            session_start();
        }

        $this->started = true;
    }

    /**
     * Apply config/session.php to the session cookie.
     *
     * None of this was previously wired up: config/session.php shipped with
     * http_only, same_site, secure, lifetime and cookie-name settings that the
     * Session class never read, so every application ran on PHP's ini
     * defaults: in particular no SameSite attribute (CSRF exposure) and,
     * depending on php.ini, no HttpOnly (session theft via XSS).
     */
    protected function applyCookieParams(): void
    {
        if (headers_sent()) {
            return;
        }

        $lifetime = (int) ($this->config['lifetime'] ?? 120);
        $sameSite = (string) ($this->config['same_site'] ?? 'Lax');

        if (! in_array(strtolower($sameSite), ['lax', 'strict', 'none'], true)) {
            $sameSite = 'Lax';
        }

        $secure = $this->config['secure'] ?? null;
        $secure = $secure === null
            // SameSite=None is only honoured on secure cookies.
            ? (strtolower($sameSite) === 'none' || $this->requestIsSecure())
            : (bool) $secure;

        session_set_cookie_params([
            'lifetime' => ($this->config['expire_on_close'] ?? false) ? 0 : $lifetime * 60,
            'path'     => (string) ($this->config['path'] ?? '/'),
            'domain'   => (string) ($this->config['domain'] ?? ''),
            'secure'   => $secure,
            'httponly' => (bool) ($this->config['http_only'] ?? true),
            'samesite' => ucfirst(strtolower($sameSite)),
        ]);

        $name = $this->config['cookie'] ?? null;

        if (is_string($name) && $name !== '' && preg_match('/^[A-Za-z0-9_\-]+$/', $name)) {
            session_name($name);
        }
    }

    protected function requestIsSecure(): bool
    {
        $https = $_SERVER['HTTPS'] ?? '';

        return ($https !== '' && strtolower((string) $https) !== 'off')
            || ($_SERVER['SERVER_PORT'] ?? null) == 443;
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
    /**
     * Destroy the session and issue a brand-new session ID.
     *
     * The old body called session_destroy() and then checked for
     * PHP_SESSION_NONE, but session_status() stays ACTIVE for the rest of the
     * request after session_destroy(), so the restart never happened and, more
     * importantly, the *session ID was never rotated*. Logging out therefore
     * left the pre-logout identifier valid, which is textbook session fixation.
     */
    public function invalidate(): void
    {
        $this->flush();

        if (PHP_SAPI === 'cli' || session_status() !== PHP_SESSION_ACTIVE) {
            $this->started = PHP_SAPI !== 'cli';
            return;
        }

        // true => delete the old session file as well as rotating the ID.
        session_regenerate_id(true);

        $_SESSION      = [];
        $this->started = true;
    }

    // ─────────────────────────────────────────────────────────────────
    //  Flash data
    // ─────────────────────────────────────────────────────────────────

    public function flash(string $key, mixed $value): void
    {
        $_SESSION['_flash']['next'][$key] = $value;
    }

    /** Whether flash data has already been aged during this request. */
    protected bool $flashAged = false;

    /**
     * Promote flash data staged by the previous request into the readable
     * 'old' bucket, exactly once per request.
     *
     * Several call sites used to invoke this: SessionServiceProvider::boot(),
     * SessionMiddleware, and ShareErrorsMiddleware, so on a 'web' route it ran
     * two or three times. The second run moved the now-empty 'next' bucket over
     * 'old', deleting the messages before any view could read them: that is why
     * `return back()->with('error', ...)` appeared to do nothing.
     */
    public function ageFlashData(): void
    {
        if ($this->flashAged) {
            return;
        }

        $this->flashAged = true;

        $_SESSION['_flash']['old']  = $_SESSION['_flash']['next'] ?? [];
        $_SESSION['_flash']['next'] = [];
    }

    /**
     * Keep the current request's flash data for one more request.
     */
    public function reflash(): void
    {
        $_SESSION['_flash']['next'] = array_merge(
            $_SESSION['_flash']['next'] ?? [],
            $_SESSION['_flash']['old'] ?? []
        );
    }

    /**
     * All flash values readable on this request.
     */
    public function allFlash(): array
    {
        return $_SESSION['_flash']['old'] ?? [];
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
        if (PHP_SAPI === 'cli' || session_status() !== PHP_SESSION_ACTIVE) {
            return false;
        }

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
