<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use Libxa\Foundation\Application;
use Libxa\Support\Facades\Hash;

class HelpersTest extends TestCase
{
    protected function setUp(): void
    {
        $app = Application::getInstance();
        $app->singleton('encrypter', function() {
            return new \Libxa\Security\Encrypter('12345678901234567890123456789012'); // 32 chars for AES-256
        });
        $app->singleton('tenant', function($app) {
            return new \Libxa\Multitenancy\TenantManager($app);
        });
    }

    public function test_tap_helper()
    {
        $object = (object) ['name' => 'Initial'];
        $result = tap($object, function($obj) {
            $obj->name = 'Modified';
        });

        $this->assertSame($object, $result);
        $this->assertEquals('Modified', $result->name);
    }

    public function test_rescue_helper()
    {
        $result = rescue(function() {
            throw new \Exception('Error');
        }, 'Rescued', false);

        $this->assertEquals('Rescued', $result);

        $result = rescue(function() {
            return 'Success';
        });

        $this->assertEquals('Success', $result);
    }

    public function test_retry_helper()
    {
        $attempts = 0;
        $result = retry(3, function($current) use (&$attempts) {
            $attempts = $current;
            if ($attempts < 3) throw new \Exception('Try again');
            return 'Done';
        });

        $this->assertEquals(3, $attempts);
        $this->assertEquals('Done', $result);
    }

    public function test_encryption_helpers()
    {
        $secret = 'My Secret Data';
        $encrypted = encrypt($secret);
        
        $this->assertNotEquals($secret, $encrypted);
        $this->assertEquals($secret, decrypt($encrypted));
    }

    public function test_hash_facade()
    {
        $password = 'password123';
        $hash = Hash::make($password);
        
        $this->assertTrue(Hash::check($password, $hash));
        $this->assertFalse(Hash::check('wrong-password', $hash));
    }

    public function test_html_helpers()
    {
        $this->assertEquals('&lt;script&gt;alert(1)&lt;/script&gt;', e('<script>alert(1)</script>'));
        $this->assertEquals('alert(1)', sanitize('<script>alert(1)</script>'));
    }

    public function test_path_helpers()
    {
        $this->assertStringContainsString('src' . DIRECTORY_SEPARATOR . 'app', app_path());
        $this->assertStringContainsString('src' . DIRECTORY_SEPARATOR . 'config', config_path());
    }
}
