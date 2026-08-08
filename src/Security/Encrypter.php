<?php

declare(strict_types=1);

namespace Libxa\Security;

use RuntimeException;

/**
 * Authenticated symmetric encryption (encrypt-then-MAC).
 *
 * Stability/security notes:
 *  - The key length is now validated against the cipher. AES-256-CBC needs
 *    exactly 32 bytes; openssl silently NUL-pads a short key, so a truncated
 *    or misconfigured APP_KEY used to produce quietly weakened ciphertext
 *    that still round-tripped correctly in local testing.
 *  - "base64:..." keys (the format key:generate emits, and the format every
 *    Laravel-shaped .env uses) are decoded instead of being used as literal
 *    ASCII.
 *  - decrypt() unserializes with allowed_classes => false. The MAC makes
 *    forged payloads impractical, but if a key ever leaks, object injection
 *    turns "attacker can read your session" into remote code execution.
 *  - A payload whose iv/value/mac fields are arrays (trivially sent by an
 *    attacker as ?payload[iv][]=x) used to reach base64_decode()/hash_equals()
 *    with an array argument and crash with a TypeError — an unauthenticated
 *    500 on any endpoint that decrypts user input.
 */
class Encrypter
{
    protected string $key;
    protected string $cipher;

    /** cipher => required key length in bytes */
    protected const SUPPORTED = [
        'AES-128-CBC' => 16,
        'AES-256-CBC' => 32,
        'AES-128-GCM' => 16,
        'AES-256-GCM' => 32,
    ];

    public function __construct(string $key, string $cipher = 'AES-256-CBC')
    {
        $key    = static::normalizeKey($key);
        $cipher = strtoupper($cipher);

        if (! static::supported($key, $cipher)) {
            $expected = static::SUPPORTED[$cipher] ?? null;

            throw new RuntimeException($expected === null
                ? "Unsupported cipher [{$cipher}]. Supported: " . implode(', ', array_keys(static::SUPPORTED)) . '.'
                : "The application key must be {$expected} bytes long for {$cipher}; got " . strlen($key) . '. '
                  . 'Run `php libxa key:generate` to create a valid APP_KEY.');
        }

        $this->key    = $key;
        $this->cipher = $cipher;
    }

    /**
     * Decode a "base64:..." key into its raw bytes.
     */
    public static function normalizeKey(string $key): string
    {
        if (str_starts_with($key, 'base64:')) {
            $decoded = base64_decode(substr($key, 7), true);

            if ($decoded === false) {
                throw new RuntimeException('The application key is not valid base64.');
            }

            return $decoded;
        }

        return $key;
    }

    public static function supported(string $key, string $cipher): bool
    {
        $cipher = strtoupper($cipher);

        return isset(static::SUPPORTED[$cipher])
            && strlen($key) === static::SUPPORTED[$cipher];
    }

    /**
     * Generate a cryptographically secure key for a cipher.
     */
    public static function generateKey(string $cipher = 'AES-256-CBC'): string
    {
        $length = static::SUPPORTED[strtoupper($cipher)] ?? 32;

        return random_bytes($length);
    }

    /**
     * Encrypt the given value.
     */
    public function encrypt(mixed $value, bool $serialize = true): string
    {
        $ivLength = openssl_cipher_iv_length($this->cipher);

        if ($ivLength === false) {
            throw new RuntimeException("Could not determine the IV length for [{$this->cipher}].");
        }

        $iv = random_bytes($ivLength);

        $value = $serialize ? serialize($value) : (string) $value;
        $value = openssl_encrypt($value, $this->cipher, $this->key, 0, $iv);

        if ($value === false) {
            throw new RuntimeException('Could not encrypt the data.');
        }

        $mac = $this->hash($iv = base64_encode($iv), $value);

        $json = json_encode(compact('iv', 'value', 'mac'), JSON_UNESCAPED_SLASHES);

        if ($json === false || json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('Could not encrypt the data.');
        }

        return base64_encode($json);
    }

    /**
     * Decrypt the given value.
     */
    public function decrypt(string $payload, bool $unserialize = true): mixed
    {
        $payload = $this->getJsonPayload($payload);

        $iv = base64_decode($payload['iv'], true);

        if ($iv === false) {
            throw new RuntimeException('The payload is invalid.');
        }

        $decrypted = openssl_decrypt(
            $payload['value'], $this->cipher, $this->key, 0, $iv
        );

        if ($decrypted === false) {
            throw new RuntimeException('Could not decrypt the data.');
        }

        if (! $unserialize) {
            return $decrypted;
        }

        // allowed_classes => false: never instantiate arbitrary classes from
        // ciphertext, even authenticated ciphertext.
        $value = unserialize($decrypted, ['allowed_classes' => false]);

        if ($value === false && $decrypted !== serialize(false)) {
            throw new RuntimeException('Could not decrypt the data.');
        }

        return $value;
    }

    /**
     * Create a HMAC for the given value.
     */
    protected function hash(string $iv, string $value): string
    {
        return hash_hmac('sha256', $iv . $value, $this->key);
    }

    /**
     * Get the JSON payload from the serialized string.
     */
    protected function getJsonPayload(string $payload): array
    {
        $decoded = base64_decode($payload, true);

        if ($decoded === false) {
            throw new RuntimeException('The payload is invalid.');
        }

        $payload = json_decode($decoded, true);

        if (! $this->validPayload($payload)) {
            throw new RuntimeException('The payload is invalid.');
        }

        if (! $this->validMac($payload)) {
            throw new RuntimeException('The MAC is invalid.');
        }

        return $payload;
    }

    /**
     * Verify that the encryption payload is valid.
     */
    protected function validPayload(mixed $payload): bool
    {
        if (! is_array($payload) || ! isset($payload['iv'], $payload['value'], $payload['mac'])) {
            return false;
        }

        // Every field must be a string before it reaches base64_decode(),
        // openssl_decrypt() or hash_equals(), all of which TypeError on arrays.
        foreach (['iv', 'value', 'mac'] as $field) {
            if (! is_string($payload[$field])) {
                return false;
            }
        }

        $iv = base64_decode($payload['iv'], true);

        return $iv !== false && strlen($iv) === openssl_cipher_iv_length($this->cipher);
    }

    /**
     * Determine if the MAC for the given payload is valid.
     */
    protected function validMac(array $payload): bool
    {
        return hash_equals($this->hash($payload['iv'], $payload['value']), $payload['mac']);
    }
}
