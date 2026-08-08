<?php

declare(strict_types=1);

namespace Tests\Feature;

use Libxa\Http\Exceptions\HttpException;
use Libxa\Http\Middleware\CsrfMiddleware;
use Libxa\Http\Response;
use Tests\TestCase;

class CsrfMiddlewareTest extends TestCase
{
    private CsrfMiddleware $middleware;
    private \Closure $next;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->boot();
        $this->middleware = new CsrfMiddleware();
        $this->next       = fn($request) => new Response(200, [], 'passed');
    }

    private function token(): string
    {
        return $this->app->make('session')->token();
    }

    public function test_safe_methods_pass_without_a_token(): void
    {
        foreach (['GET', 'HEAD', 'OPTIONS'] as $method) {
            $response = $this->middleware->handle($this->makeRequest($method, '/x'), $this->next);

            $this->assertSame(200, $response->getStatus(), "{$method} must not require a token");
        }
    }

    public function test_a_matching_token_in_the_body_passes(): void
    {
        $request = $this->makeRequest('POST', '/x', ['_token' => $this->token()]);

        $this->assertSame(200, $this->middleware->handle($request, $this->next)->getStatus());
    }

    /**
     * Request::header() normalised names differently from Request::capture(),
     * so the X-CSRF-TOKEN branch was dead code and every SPA/axios request
     * that relied on the header was rejected.
     */
    public function test_a_matching_token_in_the_header_passes(): void
    {
        $request = $this->makeRequest('POST', '/x', [], ['X-CSRF-TOKEN' => $this->token()]);

        $this->assertSame(200, $this->middleware->handle($request, $this->next)->getStatus());
    }

    public function test_a_missing_token_is_rejected(): void
    {
        $this->expectException(HttpException::class);

        $this->middleware->handle($this->makeRequest('POST', '/x'), $this->next);
    }

    public function test_a_wrong_token_is_rejected(): void
    {
        $this->expectException(HttpException::class);

        $this->middleware->handle(
            $this->makeRequest('POST', '/x', ['_token' => str_repeat('0', 64)]),
            $this->next
        );
    }

    /**
     * `_token[]=x` reached hash_equals() with an array and raised an
     * uncatchable TypeError, turning a would-be 419 into an unauthenticated
     * 500 on every form endpoint in the application.
     */
    public function test_an_array_token_is_rejected_without_a_type_error(): void
    {
        try {
            $this->middleware->handle(
                $this->makeRequest('POST', '/x', ['_token' => ['injected']]),
                $this->next
            );

            $this->fail('Expected an HttpException');
        } catch (HttpException $e) {
            $this->assertSame(419, $e->getStatusCode());
        } catch (\TypeError $e) {
            $this->fail('An array token must not reach hash_equals(): ' . $e->getMessage());
        }
    }

    public function test_configured_uris_can_be_exempted(): void
    {
        $this->app->make('config')->set('session.csrf_except', ['webhooks/stripe']);

        $response = $this->middleware->handle($this->makeRequest('POST', '/webhooks/stripe'), $this->next);

        $this->assertSame(200, $response->getStatus());
    }

    public function test_wildcard_exemptions(): void
    {
        $this->app->make('config')->set('session.csrf_except', ['webhooks/*']);

        $this->assertSame(
            200,
            $this->middleware->handle($this->makeRequest('POST', '/webhooks/github/push'), $this->next)->getStatus()
        );

        $this->expectException(HttpException::class);
        $this->middleware->handle($this->makeRequest('POST', '/api/orders'), $this->next);
    }

    public function test_an_exemption_does_not_match_an_unrelated_prefix(): void
    {
        $this->app->make('config')->set('session.csrf_except', ['webhooks/*']);

        $this->expectException(HttpException::class);

        $this->middleware->handle($this->makeRequest('POST', '/webhooks-admin/secret'), $this->next);
    }
}
