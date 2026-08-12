<?php

declare(strict_types=1);

namespace Tests\Unit;

use Libxa\Session\Session;
use PHPUnit\Framework\TestCase;

/**
 * Session behaviour under CLI (where PHP sessions are inert): the flash-data
 * lifecycle is pure $_SESSION manipulation, so it is fully testable here.
 */
class SessionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        parent::tearDown();
    }

    public function test_put_get_forget(): void
    {
        $session = new Session();

        $session->put('key', 'value');
        $this->assertSame('value', $session->get('key'));
        $this->assertTrue($session->has('key'));

        $session->forget('key');
        $this->assertNull($session->get('key'));
        $this->assertSame('fallback', $session->get('key', 'fallback'));
    }

    public function test_pull_reads_and_removes(): void
    {
        $session = new Session();
        $session->put('once', 'value');

        $this->assertSame('value', $session->pull('once'));
        $this->assertFalse($session->has('once'));
    }

    public function test_flash_is_readable_after_one_ageing(): void
    {
        $session = new Session();
        $session->flash('status', 'Saved!');

        // Same request: not yet promoted.
        $this->assertNull($session->getFlash('status'));

        // Next request.
        $next = new Session();
        $next->ageFlashData();
        $this->assertSame('Saved!', $next->getFlash('status'));
    }

    /**
     * ageFlashData() was called from SessionServiceProvider::boot(),
     * SessionMiddleware *and* ShareErrorsMiddleware. The second call moved the
     * now-empty 'next' bucket over 'old', wiping the message before any view
     * could read it: the reason `back()->with('error', ...)` did nothing.
     */
    public function test_ageing_twice_in_one_request_does_not_wipe_the_flash_bag(): void
    {
        (new Session())->flash('status', 'Saved!');

        $session = new Session();
        $session->ageFlashData();
        $session->ageFlashData(); // second middleware
        $session->ageFlashData(); // third call site

        $this->assertSame('Saved!', $session->getFlash('status'));
    }

    public function test_flash_does_not_survive_two_requests(): void
    {
        (new Session())->flash('status', 'Saved!');

        $first = new Session();
        $first->ageFlashData();
        $this->assertSame('Saved!', $first->getFlash('status'));

        $second = new Session();
        $second->ageFlashData();
        $this->assertNull($second->getFlash('status'));
    }

    public function test_reflash_keeps_data_for_one_more_request(): void
    {
        (new Session())->flash('status', 'Saved!');

        $first = new Session();
        $first->ageFlashData();
        $first->reflash();

        $second = new Session();
        $second->ageFlashData();

        $this->assertSame('Saved!', $second->getFlash('status'));
    }

    public function test_token_is_generated_once_and_is_long_enough(): void
    {
        $session = new Session();

        $token = $session->token();

        $this->assertSame(64, strlen($token));
        $this->assertSame($token, $session->token(), 'the token must be stable within a request');
    }

    public function test_regenerate_token_changes_it(): void
    {
        $session = new Session();
        $before  = $session->token();

        $session->regenerateToken();

        $this->assertNotSame($before, $session->token());
    }

    public function test_flush_clears_everything(): void
    {
        $session = new Session();
        $session->put('a', 1);
        $session->put('b', 2);

        $session->flush();

        $this->assertFalse($session->has('a'));
        $this->assertFalse($session->has('b'));
    }

    public function test_invalidate_clears_the_session_data(): void
    {
        $session = new Session();
        $session->put('user_id', 7);

        $session->invalidate();

        $this->assertNull($session->get('user_id'));
    }

    /** Config used to be ignored entirely; at minimum it must not blow up. */
    public function test_it_accepts_configuration(): void
    {
        $session = new Session([
            'lifetime' => 60, 'cookie' => 'my_app_session',
            'http_only' => true, 'same_site' => 'strict', 'secure' => true,
        ]);

        $session->put('k', 'v');
        $this->assertSame('v', $session->get('k'));
    }
}
