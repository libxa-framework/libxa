<?php

declare(strict_types=1);

namespace Libxa\Auth;

/**
 * Authenticatable Interface
 *
 * Defines the contract for any model that can be authenticated (e.g., User).
 */
interface Authenticatable
{
    /**
     * Get the unique identifier for the user (e.g., ID).
     */
    public function getAuthIdentifier(): mixed;

    /**
     * Get the password for the user.
     */
    public function getAuthPassword(): string;

    /**
     * Get the column name for the "remember me" token.
     */
    public function getRememberTokenName(): string;

    /**
     * Get the "remember me" token value.
     */
    public function getRememberToken(): ?string;

    /**
     * Set the current access token for the model.
     */
    public function withAccessToken(mixed $token): self;
}
