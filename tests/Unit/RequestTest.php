<?php

declare(strict_types=1);

namespace Tests\Unit;

use Libxa\Http\Request;
use PHPUnit\Framework\TestCase;

class RequestTest extends TestCase
{
    protected function tearDown(): void
    {
        putenv('TRUSTED_PROXIES');
        unset($_ENV['TRUSTED_PROXIES']);

        parent::tearDown();
    }

    private function request(array $headers = [], array $server = []): Request
    {
        return new Request(
            method: 'POST',
            uri: '/submit',
            headers: $headers,
            server: array_merge(['REMOTE_ADDR' => '10.0.0.1'], $server),
        );
    }

    /**
     * capture() stored "CONTENT-TYPE" while header() looked up "CONTENT_TYPE",
     * so every multi-word header lookup returned the default. That one
     * mismatch disabled isAjax(), isJson() and header-based CSRF entirely.
     */
    public function test_headers_are_readable_however_they_are_spelled(): void
    {
        $request = $this->request(['CONTENT-TYPE' => 'application/json']);

        $this->assertSame('application/json', $request->header('Content-Type'));
        $this->assertSame('application/json', $request->header('CONTENT_TYPE'));
        $this->assertSame('application/json', $request->header('content-type'));
        $this->assertTrue($request->hasHeader('Content-Type'));
    }

    public function test_is_json_and_is_ajax_actually_work(): void
    {
        $json = $this->request(['Content-Type' => 'application/json; charset=utf-8']);
        $this->assertTrue($json->isJson());

        $ajax = $this->request(['X-Requested-With' => 'XMLHttpRequest']);
        $this->assertTrue($ajax->isAjax());
        $this->assertTrue($ajax->expectsJson());
    }

    public function test_expects_json_from_accept_header(): void
    {
        $this->assertTrue($this->request(['Accept' => 'application/json'])->expectsJson());
        $this->assertTrue($this->request(['Accept' => 'application/vnd.api+json'])->expectsJson());
        $this->assertFalse($this->request(['Accept' => 'text/html'])->expectsJson());
    }

    public function test_bearer_token(): void
    {
        $request = $this->request(['Authorization' => 'Bearer abc.def']);

        $this->assertSame('abc.def', $request->bearerToken());
        $this->assertNull($this->request()->bearerToken());
    }

    /**
     * X-Forwarded-For is client-supplied. Trusting it unconditionally let
     * anyone reset their own rate-limit bucket with one extra header.
     */
    public function test_forwarded_for_is_ignored_without_a_trusted_proxy(): void
    {
        $request = $this->request([], ['HTTP_X_FORWARDED_FOR' => '1.2.3.4']);

        $this->assertSame('10.0.0.1', $request->ip());
    }

    public function test_forwarded_for_is_honoured_for_a_trusted_proxy(): void
    {
        putenv('TRUSTED_PROXIES=10.0.0.1');

        $request = $this->request([], ['HTTP_X_FORWARDED_FOR' => '1.2.3.4, 10.0.0.9']);

        $this->assertSame('1.2.3.4', $request->ip());
    }

    public function test_a_malformed_forwarded_for_falls_back_to_remote_addr(): void
    {
        putenv('TRUSTED_PROXIES=*');

        $request = $this->request([], ['HTTP_X_FORWARDED_FOR' => 'not-an-ip']);

        $this->assertSame('10.0.0.1', $request->ip());
    }

    public function test_path_strips_query_string_and_index_php(): void
    {
        $request = new Request(method: 'GET', uri: '/index.php/users?page=2');

        $this->assertSame('/users', $request->path());
    }

    public function test_input_only_except_and_filled(): void
    {
        $request = new Request(
            method: 'POST',
            uri: '/',
            query: ['page' => '2'],
            post: ['name' => 'Ada', 'empty' => '', 'zero' => '0'],
        );

        $this->assertSame('Ada', $request->input('name'));
        $this->assertSame('2', $request->input('page'));
        $this->assertSame('fallback', $request->input('missing', 'fallback'));

        $this->assertSame(['name' => 'Ada'], $request->only(['name']));
        $this->assertArrayNotHasKey('name', $request->except(['name']));

        $this->assertTrue($request->filled('name'));
        $this->assertFalse($request->filled('empty'));
        $this->assertTrue($request->filled('zero'), '"0" is a filled value');
    }

    public function test_boolean_helper(): void
    {
        $request = new Request(method: 'POST', uri: '/', post: [
            'yes' => 'true', 'no' => 'false', 'one' => '1', 'zero' => '0',
        ]);

        $this->assertTrue($request->boolean('yes'));
        $this->assertFalse($request->boolean('no'));
        $this->assertTrue($request->boolean('one'));
        $this->assertFalse($request->boolean('zero'));
        $this->assertFalse($request->boolean('absent'));
    }

    public function test_method_is_normalised(): void
    {
        $this->assertSame('GET', (new Request(method: 'get', uri: '/'))->method());
        $this->assertTrue((new Request(method: 'get', uri: '/'))->isMethodSafe());
        $this->assertFalse((new Request(method: 'post', uri: '/'))->isMethodSafe());
    }

    public function test_is_secure_detects_https_and_trusted_proxy_forwarding(): void
    {
        $this->assertTrue($this->request([], ['HTTPS' => 'on'])->isSecure());
        $this->assertFalse($this->request([], ['HTTPS' => 'off'])->isSecure());
        $this->assertFalse($this->request()->isSecure());

        putenv('TRUSTED_PROXIES=10.0.0.1');
        $proxied = $this->request(
            ['X-Forwarded-Proto' => 'https'],
            ['HTTP_X_FORWARDED_FOR' => '1.2.3.4']
        );
        $this->assertTrue($proxied->isSecure());
    }
}
