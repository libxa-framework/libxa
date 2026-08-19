<?php

declare(strict_types=1);

namespace Tests\Unit;

use Libxa\Foundation\Application;
use Libxa\Http\Request;
use Libxa\Router\Router;
use PHPUnit\Framework\TestCase;

/**
 * Fallback routes are matched last, not registered last.
 *
 * The distinction is the entire point. A package registers its routes while
 * its provider boots, which is after routes/web.php has run, so a wildcard
 * written at the bottom of that file is still registered *first* and shadows
 * everything the package adds.
 */
final class RouterFallbackTest extends TestCase
{
    private function router(): Router
    {
        return new Router(new Application(__DIR__));
    }

    private function request(string $path, string $method = 'GET'): Request
    {
        return new Request(method: $method, uri: $path);
    }

    private function dispatch(Router $router, string $path, string $method = 'GET'): string
    {
        return $router->dispatch($this->request($path, $method))->getContent();
    }

    public function test_a_fallback_does_not_shadow_a_route_registered_after_it(): void
    {
        $router = $this->router();

        $router->fallback(fn () => 'fallback');
        $router->get('/admin/login', fn () => 'login');

        self::assertSame('login', $this->dispatch($router, '/admin/login'));
    }

    public function test_a_plain_wildcard_still_shadows_by_registration_order(): void
    {
        // Documents why fallback() exists: the obvious spelling has the
        // problem, and this test fails the day that silently changes.
        $router = $this->router();

        $router->get('/{path}', fn () => 'wildcard')->where('path', '.*');
        $router->get('/admin/login', fn () => 'login');

        self::assertSame('wildcard', $this->dispatch($router, '/admin/login'));
    }

    public function test_the_fallback_catches_what_nothing_else_matched(): void
    {
        $router = $this->router();

        $router->fallback(fn () => 'fallback');
        $router->get('/admin/login', fn () => 'login');

        self::assertSame('fallback', $this->dispatch($router, '/nothing/here'));
    }

    public function test_the_fallback_spans_slashes(): void
    {
        $router = $this->router();

        $router->fallback(fn () => 'fallback');

        self::assertSame('fallback', $this->dispatch($router, '/deeply/nested/path'));
    }

    public function test_several_fallbacks_keep_their_relative_order(): void
    {
        $router = $this->router();

        $router->fallback(fn () => 'first');
        $router->fallback(fn () => 'second');

        self::assertSame('first', $this->dispatch($router, '/unmatched'));
    }

    public function test_a_fallback_only_answers_the_verbs_it_declares(): void
    {
        // A GET fallback must not turn every stray POST into a 200.
        $router = $this->router();

        $router->fallback(fn () => 'fallback');

        self::assertSame(405, $router->dispatch($this->request('/unmatched', 'POST'))->getStatus());
    }

    public function test_fallbacks_are_included_in_the_route_list(): void
    {
        // route:list and route caching both read all(); a route missing from
        // it would work until someone cached the routes and then vanish.
        $router = $this->router();

        $router->get('/admin/login', fn () => 'login');
        $router->fallback(fn () => 'fallback');

        self::assertCount(2, $router->getRoutes()->all());
        self::assertCount(1, $router->getRoutes()->fallbacks());
    }
}
