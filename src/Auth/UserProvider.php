<?php

declare(strict_types=1);

namespace Libxa\Auth;

/**
 * User Provider Interface
 *
 * Defines how to retrieve users for authentication.
 */
interface UserProvider
{
    /**
     * Retrieve a user by their unique identifier.
     */
    public function retrieveById(mixed $identifier): ?Authenticatable;

    /**
     * Retrieve a user by their unique identifier and "remember me" token.
     */
    public function retrieveByToken(mixed $identifier, string $token): ?Authenticatable;

    /**
     * Update the "remember me" token for the given user in storage.
     */
    public function updateRememberToken(Authenticatable $user, string $token): void;

    /**
     * Retrieve a user by the given credentials.
     */
    public function retrieveByCredentials(array $credentials): ?Authenticatable;

    /**
     * Validate a user against the given credentials.
     */
    public function validateCredentials(Authenticatable $user, array $credentials): bool;
}
