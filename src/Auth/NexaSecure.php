<?php

declare(strict_types=1);

namespace Libxa\Auth;

use Libxa\Auth\Authenticatable;
use Libxa\Atlas\DB;

/**
 * LibxaSecure Core Service
 *
 * This class handles all sensitive token security logic within the framework.
 */
class LibxaSecure
{
    /**
     * Create a new access token for a user.
     * Returns an object containing the plainTextToken.
     */
    public static function createToken(Authenticatable $user, string $name, array $abilities = ['*'], ?int $expiresInMinutes = null): object
    {
        // 1. Generate secure random text tokens
        $plainToken   = bin2hex(random_bytes(25));
        $refreshToken = bin2hex(random_bytes(30));
        
        // 2. Determine expiration
        $expiresAt = $expiresInMinutes ? date('Y-m-d H:i:s', time() + ($expiresInMinutes * 60)) : null;

        // 3. Store securely hashed in database
        DB::table('personal_access_tokens')->insert([
            'tokenable_type' => ltrim(str_replace('\\\\', '\\', get_class($user)), '\\'),
            'tokenable_id'   => $user->getAuthIdentifier(),
            'name'           => $name,
            'token'          => self::hash($plainToken),
            'refresh_token'  => self::hash($refreshToken),
            'abilities'      => json_encode($abilities),
            'expires_at'     => $expiresAt,
            'created_at'     => date('Y-m-d H:i:s'),
            'updated_at'     => date('Y-m-d H:i:s'),
        ]);

        return (object) [
            'plainTextToken'  => $plainToken,
            'plainRefreshToken' => $refreshToken,
            'abilities'       => $abilities,
            'expires_at'      => $expiresAt
        ];
    }

    /**
     * Find a token record by its plain text value.
     */
    public static function findToken(string $plainToken): ?object
    {
        $hashed = self::hash($plainToken);

        return (object) DB::table('personal_access_tokens')
            ->where('token', $hashed)
            ->first();
    }

    /**
     * Delete/Revoke a token.
     */
    public static function revokeToken(int $id): bool
    {
        return (bool) DB::table('personal_access_tokens')
            ->where('id', $id)
            ->deleteRecord();
    }

    /**
     * Securely hash a token.
     */
    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }
}
