<?php

declare(strict_types=1);

namespace Libxa\Auth\Guards;

use Libxa\Auth\Authenticatable;
use Libxa\Auth\Guard;
use Libxa\Auth\UserProvider;
use Libxa\Http\Request;
use Libxa\Atlas\DB;

/**
 * LibxaSecure Guard
 *
 * Authenticates users via a secure API token (SHA-256) verified
 * against a personal_access_tokens table. Supports abilities and expiration.
 */
class LibxaSecureGuard implements Guard
{
    protected ?Authenticatable $user = null;

    public function __construct(
        protected UserProvider $provider,
        protected Request      $request
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

        $token = $this->request->bearerToken();

        if (empty($token)) {
            return null;
        }

        // 1. Find token in database (using core security service)
        $tokenRecord = \Libxa\Auth\LibxaSecure::findToken($token);

        if (! $tokenRecord || ! isset($tokenRecord->id)) {
            return null;
        }

        // 2. Check for expiration if enabled
        $expiresAt = is_array($tokenRecord) ? $tokenRecord['expires_at'] : $tokenRecord->expires_at;
        if ($expiresAt && strtotime($expiresAt) < time()) {
            return null;
        }

        // 3. Update last used at timestamp
        $id = is_array($tokenRecord) ? $tokenRecord['id'] : $tokenRecord->id;
        DB::table('personal_access_tokens')
            ->where('id', $id)
            ->update(['last_used_at' => date('Y-m-d H:i:s')]);

        // 4. Resolve the user
        $tokenableId = is_array($tokenRecord) ? $tokenRecord['tokenable_id'] : $tokenRecord->tokenable_id;
        $this->user = $this->provider->retrieveById($tokenableId);

        // 5. Attach token data to the user if supported
        if ($this->user && method_exists($this->user, 'withAccessToken')) {
            $this->user->withAccessToken($tokenRecord);
        }

        return $this->user;
    }

    public function id(): mixed
    {
        return $this->user()?->getAuthIdentifier();
    }

    public function validate(array $credentials = []): bool
    {
        return false; // Stateless guard
    }

    public function setUser(Authenticatable $user): void
    {
        $this->user = $user;
    }
}
