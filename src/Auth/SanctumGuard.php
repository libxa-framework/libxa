<?php

declare(strict_types=1);

namespace Libxa\Auth;

use Libxa\Http\Request;
use Libxa\Atlas\DB;

/**
 * Sanctum Guard
 *
 * Authenticates users via an API token verified against a personal_access_tokens table.
 */
class SanctumGuard implements Guard
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

        if (! empty($token)) {
            // Find token in database (hashed)
            $hashedToken = hash('sha256', $token);
            
            $accessToken = DB::table('personal_access_tokens')
                ->where('token', $hashedToken)
                ->first();

            if ($accessToken) {
                // Safely update last used at independently of objects vs arrays
                $id = is_array($accessToken) ? $accessToken['id'] : $accessToken->id;
                $tokenable_id = is_array($accessToken) ? $accessToken['tokenable_id'] : $accessToken->tokenable_id;

                DB::table('personal_access_tokens')
                    ->where('id', $id)
                    ->update(['last_used_at' => date('Y-m-d H:i:s')]);

                // Retrieve user by ID
                $this->user = $this->provider->retrieveById($tokenable_id);
            }
        }

        return $this->user;
    }

    public function id(): mixed
    {
        return $this->user()?->getAuthIdentifier();
    }

    public function validate(array $credentials = []): bool
    {
        // Not typically used for stateless APIs, usually driven by bearer tokens.
        return false;
    }

    public function setUser(Authenticatable $user): void
    {
        $this->user = $user;
    }
}
