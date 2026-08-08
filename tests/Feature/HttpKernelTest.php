<?php

declare(strict_types=1);

namespace Tests\Feature;

use Libxa\Http\Response;
use Libxa\Router\Router;
use Tests\TestCase;

/**
 * Drives the real HTTP kernel — boot, global middleware, routing, dispatch,
 * exception handling — against a throwaway application skeleton.
 */
class HttpKernelTest extends TestCase
{
    protected array $env = ['RATE_LIMIT_ENABLED' => 'false'];

    private function router(): Router
    {
        $this->app->boot();

        return $this->app->make(Router::class);
    }

    private function handle(\Libxa\Http\Request $request): Response
    {
        return $this->app->make(\Libxa\Foundation\HttpKernel::class)->handle($request);
    }

    public function test_a_string_return_becomes_an_html_response(): void
    {
        $this->router()->get('/hello', fn() => 'Hi there');

        $response = $this->handle($this->makeRequest('GET', '/hello'));

        $this->assertSame(200, $response->getStatus());
        $this->assertSame('Hi there', $response->getContent());
        $this->assertStringContainsString('text/html', (string) $response->getHeader('Content-Type'));
    }

    public function test_an_array_return_becomes_json(): void
    {
        $this->router()->get('/data', fn() => ['ok' => true, 'n' => 3]);

        $response = $this->handle($this->makeRequest('GET', '/data'));

        $this->assertSame('application/json', $response->getHeader('Content-Type'));
        $this->assertSame(['ok' => true, 'n' => 3], json_decode($response->getContent(), true));
    }

    public function test_route_parameters_are_injected(): void
    {
        $this->router()->get('/users/{id}', fn($id) => "user:$id");

        $this->assertSame('user:42', $this->handle($this->makeRequest('GET', '/users/42'))->getContent());
    }

    public function test_unknown_path_is_404(): void
    {
        $this->router()->get('/known', fn() => 'ok');

        $this->assertSame(404, $this->handle($this->makeRequest('GET', '/nope'))->getStatus());
    }

    /**
     * A form POSTing to a GET-only route used to look exactly like a typo in
     * the URL, because both produced a 404.
     */
    public function test_wrong_verb_is_405_with_an_allow_header(): void
    {
        // GET so the request is "safe" and CSRF (global middleware, which runs
        // before routing) does not answer 419 first.
        $this->router()->post('/only-post', fn() => 'ok');

        $response = $this->handle($this->makeRequest('GET', '/only-post'));

        $this->assertSame(405, $response->getStatus());
        $this->assertStringContainsString('POST', (string) $response->getHeader('Allow'));
    }

    /**
     * CSRF is global middleware, so it runs before the router — an unsafe
     * verb without a token is rejected regardless of whether the route exists.
     */
    public function test_an_unsafe_verb_without_a_csrf_token_is_419(): void
    {
        $this->router()->post('/submit', fn() => 'ok');

        $this->assertSame(419, $this->handle($this->makeRequest('POST', '/submit'))->getStatus());
    }

    public function test_an_http_exception_renders_its_status(): void
    {
        $this->router()->get('/boom', fn() => abort(403, 'Nope'));

        $response = $this->handle($this->makeRequest('GET', '/boom'));

        $this->assertSame(403, $response->getStatus());
        $this->assertStringContainsString('Nope', $response->getContent());
    }

    public function test_an_http_exception_renders_json_for_a_json_client(): void
    {
        $this->router()->get('/boom', fn() => abort(403, 'Nope'));

        $response = $this->handle(
            $this->makeRequest('GET', '/boom', headers: ['Accept' => 'application/json'])
        );

        $this->assertSame(403, $response->getStatus());
        $this->assertSame(['message' => 'Nope'], json_decode($response->getContent(), true));
    }

    /** Headers attached to an HttpException used to be dropped entirely. */
    public function test_exception_headers_reach_the_response(): void
    {
        $this->router()->get('/limited', function () {
            throw new \Libxa\Http\Exceptions\HttpException(429, 'Slow down', headers: ['Retry-After' => '30']);
        });

        $response = $this->handle($this->makeRequest('GET', '/limited'));

        $this->assertSame(429, $response->getStatus());
        $this->assertSame('30', $response->getHeader('Retry-After'));
    }

    public function test_an_unexpected_exception_is_a_500(): void
    {
        $this->router()->get('/explode', function () {
            throw new \LogicException('internal detail');
        });

        $response = $this->handle($this->makeRequest('GET', '/explode'));

        $this->assertSame(500, $response->getStatus());
    }

    /** APP_DEBUG=false must not leak the exception message to the client. */
    public function test_production_mode_hides_the_exception_message(): void
    {
        $this->app->make('config')->set('app.debug', false);

        $this->router()->get('/explode', function () {
            throw new \LogicException('SECRET-DB-PASSWORD-LEAK');
        });

        $response = $this->handle($this->makeRequest('GET', '/explode'));

        $this->assertSame(500, $response->getStatus());
        $this->assertStringNotContainsString('SECRET-DB-PASSWORD-LEAK', $response->getContent());
    }

    public function test_debug_mode_shows_the_exception_message(): void
    {
        $this->router()->get('/explode', function () {
            throw new \LogicException('the real cause');
        });

        $this->assertStringContainsString(
            'the real cause',
            $this->handle($this->makeRequest('GET', '/explode'))->getContent()
        );
    }

    /**
     * The debug page interpolated the exception message straight into HTML.
     */
    public function test_the_debug_page_escapes_the_exception_message(): void
    {
        $this->router()->get('/xss', function () {
            throw new \RuntimeException('<script>alert(1)</script>');
        });

        $body = $this->handle($this->makeRequest('GET', '/xss'))->getContent();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $body);
        $this->assertStringContainsString('&lt;script&gt;', $body);
    }

    /**
     * Route::middleware('web') asked the container for a class named "web"
     * and died with "Target class [web] does not exist".
     */
    public function test_a_middleware_group_name_is_expanded(): void
    {
        $this->router()->get('/grouped', fn() => 'ok')->middleware('web');

        $response = $this->handle($this->makeRequest('GET', '/grouped'));

        $this->assertSame(200, $response->getStatus());
        $this->assertSame('ok', $response->getContent());
    }

    public function test_middleware_can_short_circuit_the_pipeline(): void
    {
        $this->router()->get('/blocked', fn() => 'never')->middleware(
            fn($request, $next) => new Response(403, [], 'stopped')
        );

        $response = $this->handle($this->makeRequest('GET', '/blocked'));

        $this->assertSame(403, $response->getStatus());
        $this->assertSame('stopped', $response->getContent());
    }

    public function test_middleware_runs_in_declaration_order(): void
    {
        $order = [];

        $this->router()->get('/ordered', function () use (&$order) {
            $order[] = 'handler';
            return 'ok';
        })->middleware([
            function ($request, $next) use (&$order) { $order[] = 'first';  return $next($request); },
            function ($request, $next) use (&$order) { $order[] = 'second'; return $next($request); },
        ]);

        $this->handle($this->makeRequest('GET', '/ordered'));

        $this->assertSame(['first', 'second', 'handler'], $order);
    }

    /** A middleware returning the wrong type gave an opaque TypeError. */
    public function test_a_middleware_returning_a_non_response_names_itself(): void
    {
        $this->router()->get('/bad-mw', fn() => 'ok')->middleware(
            fn($request, $next) => 'not a response'
        );

        $response = $this->handle($this->makeRequest('GET', '/bad-mw'));

        $this->assertSame(500, $response->getStatus());
        $this->assertStringContainsString('must return a', $response->getContent());
    }

    public function test_an_unknown_middleware_name_is_reported_clearly(): void
    {
        $this->router()->get('/mystery', fn() => 'ok')->middleware('does-not-exist');

        $response = $this->handle($this->makeRequest('GET', '/mystery'));

        $this->assertSame(500, $response->getStatus());
        $this->assertStringContainsString('not a known alias, group or class', $response->getContent());
    }

    public function test_a_missing_controller_class_is_reported_clearly(): void
    {
        $this->router()->get('/ghost', ['App\\Nope\\GhostController', 'index']);

        $response = $this->handle($this->makeRequest('GET', '/ghost'));

        $this->assertSame(500, $response->getStatus());
        $this->assertStringContainsString('does not exist', $response->getContent());
    }
}
