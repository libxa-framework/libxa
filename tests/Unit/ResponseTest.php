<?php

declare(strict_types=1);

namespace Tests\Unit;

use Libxa\Http\Response;
use PHPUnit\Framework\TestCase;

class ResponseTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $_SERVER['HTTP_HOST'] = 'app.test';
    }

    protected function tearDown(): void
    {
        unset($_SERVER['HTTP_HOST'], $_SERVER['HTTP_REFERER']);
        parent::tearDown();
    }

    public function test_basic_construction(): void
    {
        $response = new Response(201, ['X-Test' => 'yes'], 'body');

        $this->assertSame(201, $response->getStatus());
        $this->assertSame('yes', $response->getHeader('X-Test'));
        $this->assertSame('body', $response->getContent());
    }

    public function test_redirect(): void
    {
        $response = Response::redirect('/dashboard');

        $this->assertSame(302, $response->getStatus());
        $this->assertSame('/dashboard', $response->getHeader('Location'));
    }

    /**
     * Every cookie was written to $headers['Set-Cookie'], so each call
     * overwrote the last and a response could only ever carry one cookie.
     */
    public function test_multiple_cookies_survive_on_one_response(): void
    {
        $response = (new Response())
            ->cookie('first', 'a')
            ->cookie('second', 'b')
            ->cookie('third', 'c');

        $cookies = $response->getCookies();

        $this->assertCount(3, $cookies);
        $this->assertStringContainsString('first=a', $cookies['first']);
        $this->assertStringContainsString('second=b', $cookies['second']);
        $this->assertStringContainsString('third=c', $cookies['third']);
    }

    public function test_cookies_default_to_httponly_and_samesite(): void
    {
        $cookie = (new Response())->cookie('session', 'value')->getCookies()['session'];

        $this->assertStringContainsString('HttpOnly', $cookie);
        $this->assertStringContainsString('SameSite=Lax', $cookie);
    }

    public function test_forget_cookie_expires_it(): void
    {
        $cookie = (new Response())->forgetCookie('stale')->getCookies()['stale'];

        $this->assertStringContainsString('Max-Age=0', $cookie);
    }

    /**
     * back() echoed the client-supplied Referer straight into a Location
     * header, so any page that redirected back was an open redirect.
     */
    public function test_back_ignores_a_cross_origin_referer(): void
    {
        $_SERVER['HTTP_REFERER'] = 'https://evil.example/phish';

        $this->assertSame('/', Response::back()->getHeader('Location'));
    }

    public function test_back_follows_a_same_origin_referer(): void
    {
        $_SERVER['HTTP_REFERER'] = 'http://app.test/settings';

        $this->assertSame('http://app.test/settings', Response::back()->getHeader('Location'));
    }

    public function test_back_accepts_a_relative_referer(): void
    {
        $_SERVER['HTTP_REFERER'] = '/settings?tab=2';

        $this->assertSame('/settings?tab=2', Response::back()->getHeader('Location'));
    }

    /** "//evil.com/x" has no host per parse_url, but browsers treat it as one. */
    public function test_back_rejects_a_protocol_relative_referer(): void
    {
        $_SERVER['HTTP_REFERER'] = '//evil.example/phish';

        $this->assertSame('/', Response::back()->getHeader('Location'));
    }

    public function test_back_falls_back_when_there_is_no_referer(): void
    {
        $this->assertSame('/home', Response::back('/home')->getHeader('Location'));
    }

    public function test_fluent_setters(): void
    {
        $response = (new Response())
            ->withStatus(418)
            ->withHeaders(['A' => '1', 'B' => '2'])
            ->withContent('teapot');

        $this->assertSame(418, $response->getStatus());
        $this->assertSame('1', $response->getHeader('A'));
        $this->assertSame('2', $response->getHeader('B'));
        $this->assertSame('teapot', $response->getContent());
    }
}
