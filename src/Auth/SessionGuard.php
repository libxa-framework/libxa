<?php

declare(strict_types=1);

namespace Libxa\Auth;

use Libxa\Session\Session;

/**
 * Session Guard
 *
 * Uses sessions to persist authenticated state.
 */
class SessionGuard implements Guard
{
    protected ?Authenticatable $user = null;

    public function __construct(
        protected string       $name,
        protected UserProvider $provider,
        protected Session      $session
    ) {}

    public function check(): bool
    {
        return ! is_null($this->user());
    }

    public function guest(): bool
    {
        return ! $this->check();
    }

    public function user(): ?Authenticatable
    {
        if (! is_null($this->user)) {
            return $this->user;
        }

        $id = $this->session->get($this->getName());

        if (! is_null($id)) {
            $this->user = $this->provider->retrieveById($id);
        }

        return $this->user;
    }

    public function id(): mixed
    {
        return $this->user()?->getAuthIdentifier();
    }

    public function validate(array $credentials = []): bool
    {
        return ! is_null($this->provider->retrieveByCredentials($credentials));
    }

    public function attempt(array $credentials = [], bool $remember = false): bool
    {
        $user = $this->provider->retrieveByCredentials($credentials);

        if ($user && $this->provider->validateCredentials($user, $credentials)) {
            $this->login($user, $remember);
            return true;
        }

        return false;
    }

    public function login(Authenticatable $user, bool $remember = false): void
    {
        $this->updateSession($user->getAuthIdentifier());

        if ($remember) {
            $this->ensureRememberTokenIsSet($user);
            // Cookie implementation would go here (omitted for now)
        }

        $this->setUser($user);
    }

    public function loginUsingId(mixed $id, bool $remember = false): ?Authenticatable
    {
        $user = $this->provider->retrieveById($id);

        if (! is_null($user)) {
            $this->login($user, $remember);
            return $user;
        }

        return null;
    }

    public function logout(): void
    {
        $this->session->forget($this->getName());
        $this->user = null;
    }

    public function setUser(Authenticatable $user): void
    {
        $this->user = $user;
    }

    protected function updateSession(mixed $id): void
    {
        $this->session->put($this->getName(), $id);
        $this->session->regenerate(true);
    }

    protected function getName(): string
    {
        return 'login_' . $this->name . '_' . sha1(static::class);
    }

    protected function ensureRememberTokenIsSet(Authenticatable $user): void
    {
        if (empty($user->getRememberToken())) {
            $this->provider->updateRememberToken($user, bin2hex(random_bytes(32)));
        }
    }
}
