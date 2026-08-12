<?php

declare(strict_types=1);

namespace Tests\Unit;

use Libxa\Security\Encrypter;
use PHPUnit\Framework\TestCase;

class EncrypterTest extends TestCase
{
    private function encrypter(): Encrypter
    {
        return new Encrypter(str_repeat('a', 32));
    }

    public function test_round_trips_values(): void
    {
        $encrypter = $this->encrypter();

        foreach (['hello', 42, 3.14, true, false, null, ['a' => 1, 'b' => [2, 3]]] as $value) {
            $this->assertSame(
                $value,
                $encrypter->decrypt($encrypter->encrypt($value)),
                'failed to round-trip ' . var_export($value, true)
            );
        }
    }

    public function test_ciphertext_differs_between_calls(): void
    {
        $encrypter = $this->encrypter();

        $this->assertNotSame($encrypter->encrypt('same'), $encrypter->encrypt('same'));
    }

    /**
     * openssl silently NUL-pads a short key, so a truncated APP_KEY used to
     * produce quietly weakened ciphertext that still round-tripped locally.
     */
    public function test_a_short_key_is_rejected(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/must be 32 bytes/');

        new Encrypter('too-short');
    }

    public function test_base64_prefixed_keys_are_decoded(): void
    {
        $raw       = random_bytes(32);
        $encrypter = new Encrypter('base64:' . base64_encode($raw));

        $this->assertSame('ok', $encrypter->decrypt($encrypter->encrypt('ok')));

        // The same raw bytes must produce an interchangeable instance.
        $this->assertSame('ok', (new Encrypter($raw))->decrypt($encrypter->encrypt('ok')));
    }

    public function test_unsupported_cipher_is_rejected(): void
    {
        $this->expectException(\RuntimeException::class);

        new Encrypter(str_repeat('a', 32), 'ROT-13');
    }

    public function test_a_tampered_payload_is_rejected(): void
    {
        $encrypter = $this->encrypter();
        $payload   = json_decode(base64_decode($encrypter->encrypt('secret')), true);

        $payload['value'] = base64_encode('tampered');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/MAC is invalid/');

        $encrypter->decrypt(base64_encode(json_encode($payload)));
    }

    public function test_a_payload_from_another_key_is_rejected(): void
    {
        $payload = (new Encrypter(str_repeat('b', 32)))->encrypt('secret');

        $this->expectException(\RuntimeException::class);

        $this->encrypter()->decrypt($payload);
    }

    /**
     * `?payload[iv][]=x` used to reach base64_decode()/hash_equals() with an
     * array and raise an uncatchable TypeError: an unauthenticated 500 on
     * any endpoint that decrypts user input.
     */
    public function test_array_payload_fields_are_rejected_without_a_type_error(): void
    {
        $malicious = base64_encode(json_encode([
            'iv'    => ['x'],
            'value' => ['y'],
            'mac'   => ['z'],
        ]));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/payload is invalid/');

        $this->encrypter()->decrypt($malicious);
    }

    public function test_garbage_input_is_rejected(): void
    {
        $this->expectException(\RuntimeException::class);

        $this->encrypter()->decrypt('not-even-base64-json!!!');
    }

    public function test_generated_keys_are_the_right_length(): void
    {
        $this->assertSame(32, strlen(Encrypter::generateKey('AES-256-CBC')));
        $this->assertSame(16, strlen(Encrypter::generateKey('AES-128-CBC')));
    }
}
