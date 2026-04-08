<?php

declare(strict_types=1);

namespace Libxa\Auth;

use Libxa\Http\Request;

/**
 * Token Guard
 *
 * Authenticates users via an API token passed in the request.
 */
class TokenGuard implements Guard
{
    protected ?Authenticatable $user = null;

    public function __construct(
        protected UserProvider $provider,
        protected Request      $request,
        protected string       $inputKey   = 'api_token',
        protected string       $storageKey = 'api_token'
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

        $token = $this->getTokenForRequest();

        if (! empty($token)) {
            $this->user = $this->provider->retrieveByCredentials([
                $this->storageKey => $token,
            ]);
        }

        return $this->user;
    }

    public function id(): mixed
    {
        return $this->user()?->getAuthIdentifier();
    }

    public function validate(array $credentials = []): bool
    {
        if (empty($credentials[$this->inputKey])) {
            return false;
        }

        return ! is_null($this->provider->retrieveByCredentials([
            $this->storageKey => $credentials[$this->inputKey],
        ]));
    }

    public function setUser(Authenticatable $user): void
    {
        $this->user = $user;
    }

    /**
     * Get the token for the current request.
     */
    protected function getTokenForRequest(): string
    {
        $token = $this->request->query($this->inputKey);

        if (empty($token)) {
            $token = $this->request->input($this->inputKey);
        }

        if (empty($token)) {
            $token = $this->request->bearerToken();
        }

        return $token ?? '';
    }
}
