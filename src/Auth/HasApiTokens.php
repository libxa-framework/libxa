<?php

declare(strict_types=1);

namespace Libxa\Auth;

use Libxa\Auth\LibxaSecure;
use Libxa\Atlas\DB;

/**
 * HasApiTokens Trait
 *
 * Provides LibxaSecure token management for models by delegating to the framework core.
 * This is now a Standalone Core feature of LibxaFrame.
 */
trait HasApiTokens
{
    /** The current access token */
    protected $accessToken;

    /**
     * Issue a new personal access token for the user.
     * Delegates security logic to LibxaSecure core.
     */
    public function createToken(string $name, array $abilities = ['*'], ?int $expiresInMinutes = null)
    {
        return LibxaSecure::createToken($this, $name, $abilities, $expiresInMinutes);
    }

    /**
     * Check if the current token has a given ability.
     */
    public function tokenCan(string $ability): bool
    {
        if (! $this->accessToken) {
            return false;
        }

        $abilities = json_decode($this->accessToken->abilities ?? '[]', true);

        return in_array('*', $abilities) || in_array($ability, $abilities);
    }

    /**
     * Query builder for all API tokens associated with this user.
     */
    public function tokens()
    {
        return DB::table('personal_access_tokens')
            ->where('tokenable_id', $this->id)
            ->where('tokenable_type', ltrim(str_replace(['\\\\', '\\'], '\\', get_class($this)), '\\'));
    }

    /**
     * Revoke all tokens for the model.
     */
    public function revokeAllTokens(): bool
    {
        return (bool) $this->tokens()->deleteRecord();
    }

    /**
     * Set the current access token for the model.
     * (Called by LibxaSecureGuard)
     */
    public function withAccessToken(mixed $token): self
    {
        $this->accessToken = $token;
        return $this;
    }

    /**
     * Get the current access token.
     */
    public function currentAccessToken()
    {
        return $this->accessToken;
    }
}
