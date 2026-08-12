<?php

declare(strict_types=1);

namespace Tests\Unit;

use Libxa\Http\Request;
use Libxa\Router\Route;
use Libxa\Router\RouteCollection;
use PHPUnit\Framework\TestCase;

class RouteTest extends TestCase
{
    private function request(string $method, string $uri): Request
    {
        return new Request(method: $method, uri: $uri, server: ['REMOTE_ADDR' => '127.0.0.1']);
    }

    public function test_static_route_matches(): void
    {
        $route = new Route(['GET'], '/about', fn() => 'ok');

        $this->assertTrue($route->matches($this->request('GET', '/about')));
        $this->assertFalse($route->matches($this->request('GET', '/contact')));
    }

    public function test_required_parameter_is_extracted(): void
    {
        $route = new Route(['GET'], '/users/{id}', fn() => 'ok');

        $this->assertTrue($route->matches($this->request('GET', '/users/42')));
        $this->assertSame(['id' => '42'], $route->getParameters());
    }

    /**
     * "/users/{id?}" compiled to "/users/(?P<id>[^/]+)?": the slash was
     * mandatory, so the "no parameter" case could never match.
     */
    public function test_optional_parameter_matches_with_and_without_the_segment(): void
    {
        $route = new Route(['GET'], '/users/{id?}', fn() => 'ok');

        $this->assertTrue($route->matches($this->request('GET', '/users')), 'bare /users should match');
        $this->assertSame([], $route->getParameters());

        $this->assertTrue($route->matches($this->request('GET', '/users/7')));
        $this->assertSame(['id' => '7'], $route->getParameters());
    }

    /** Literal dots were treated as regex wildcards. */
    public function test_literal_characters_are_escaped(): void
    {
        $route = new Route(['GET'], '/files/{name}.json', fn() => 'ok');

        $this->assertTrue($route->matches($this->request('GET', '/files/report.json')));
        $this->assertFalse($route->matches($this->request('GET', '/files/reportXjson')));
    }

    public function test_where_constraints_are_applied(): void
    {
        $route = (new Route(['GET'], '/orders/{id}', fn() => 'ok'))->where('id', '\d+');

        $this->assertTrue($route->matches($this->request('GET', '/orders/12')));
        $this->assertFalse($route->matches($this->request('GET', '/orders/abc')));
    }

    public function test_method_mismatch_does_not_match(): void
    {
        $route = new Route(['POST'], '/submit', fn() => 'ok');

        $this->assertFalse($route->matches($this->request('GET', '/submit')));
        $this->assertTrue($route->matches($this->request('POST', '/submit')));
    }

    public function test_name_prefix_from_a_group_is_applied(): void
    {
        $route = (new Route(['GET'], '/dash', fn() => 'ok'))
            ->setNamePrefix('admin.')
            ->name('dashboard');

        $this->assertSame('admin.dashboard', $route->getName());
    }

    public function test_unnamed_route_stays_unnamed_even_with_a_prefix(): void
    {
        $route = (new Route(['GET'], '/x', fn() => 'ok'))->setNamePrefix('admin.');

        $this->assertSame('', $route->getName());
    }

    /**
     * Routes live for the whole process. Under a persistent worker the
     * parameters of request N used to still be readable during request N+1.
     */
    public function test_parameters_are_bound_to_the_request_not_only_the_route(): void
    {
        $collection = new RouteCollection();
        $collection->add(new Route(['GET'], '/posts/{slug}', fn() => 'ok'));

        $first = $this->request('GET', '/posts/hello');
        $collection->match($first);

        $second = $this->request('GET', '/posts/world');
        $collection->match($second);

        $this->assertSame(['slug' => 'hello'], $first->getAttribute('_route_params'));
        $this->assertSame(['slug' => 'world'], $second->getAttribute('_route_params'));
    }

    public function test_allowed_methods_reports_405_candidates(): void
    {
        $collection = new RouteCollection();
        $collection->add(new Route(['GET'], '/items', fn() => 'ok'));
        $collection->add(new Route(['POST'], '/items', fn() => 'ok'));

        $this->assertEqualsCanonicalizing(['GET', 'POST'], $collection->allowedMethods('/items'));
        $this->assertSame([], $collection->allowedMethods('/unknown'));
    }

    public function test_named_lookup_finds_routes_named_after_registration(): void
    {
        $collection = new RouteCollection();
        $route      = new Route(['GET'], '/profile', fn() => 'ok');

        $collection->add($route);
        $this->assertNull($collection->getByName('profile')); // primes the index
        $route->name('profile');

        $this->assertSame($route, $collection->getByName('profile'));
    }
}
