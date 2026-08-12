<?php

declare(strict_types=1);

namespace Tests\Feature;

use Libxa\Router\Attributes\Middleware;
use Libxa\Router\Attributes\Prefix;
use Libxa\Router\Attributes\Route as RouteAttribute;
use Libxa\Router\Router;
use Tests\TestCase;

class RouterTest extends TestCase
{
    private function router(): Router
    {
        return new Router($this->app);
    }

    private function uris(Router $router): array
    {
        return array_map(fn($r) => $r->getUri(), $router->getRoutes()->all());
    }

    public function test_group_prefixes_are_applied(): void
    {
        $router = $this->router();

        $router->group(['prefix' => 'admin'], function (Router $r) {
            $r->get('/users', fn() => 'ok');
        });

        $this->assertContains('/admin/users', $this->uris($router));
    }

    /**
     * prefix()/middleware()/name() pushed onto the group stack, and group()
     * only ever popped one frame, so `Route::prefix('api')->group(...)` left
     * "api" on the stack permanently and silently prefixed every route
     * registered afterwards, anywhere in the application.
     */
    public function test_the_fluent_prefix_builder_does_not_leak_into_later_routes(): void
    {
        $router = $this->router();

        $router->prefix('api')->group([], function (Router $r) {
            $r->get('/inside', fn() => 'ok');
        });

        $router->get('/outside', fn() => 'ok');

        $uris = $this->uris($router);

        $this->assertContains('/api/inside', $uris);
        $this->assertContains('/outside', $uris);
        $this->assertNotContains('/api/outside', $uris);
    }

    /** A route file that throws mid-group used to leak the prefix forever. */
    public function test_a_throwing_group_callback_still_pops_the_stack(): void
    {
        $router = $this->router();

        try {
            $router->group(['prefix' => 'broken'], function () {
                throw new \RuntimeException('route file blew up');
            });
        } catch (\RuntimeException) {
            // expected
        }

        $router->get('/after', fn() => 'ok');

        $this->assertContains('/after', $this->uris($router));
    }

    public function test_group_middleware_is_applied_to_each_route(): void
    {
        $router = $this->router();

        $router->group(['middleware' => ['auth']], function (Router $r) {
            $r->get('/secret', fn() => 'ok');
        });

        $this->assertSame(['auth'], $router->getRoutes()->all()[0]->getMiddleware());
    }

    public function test_the_fluent_middleware_builder_does_not_leak(): void
    {
        $router = $this->router();

        $router->middleware('auth')->group([], function (Router $r) {
            $r->get('/inside', fn() => 'ok');
        });

        $router->get('/outside', fn() => 'ok');

        $routes = $router->getRoutes()->all();

        $this->assertSame(['auth'], $routes[0]->getMiddleware());
        $this->assertSame([], $routes[1]->getMiddleware(), 'middleware must not leak past the group');
    }

    public function test_group_name_prefixes_compose(): void
    {
        $router = $this->router();

        $router->group(['name' => 'admin.'], function (Router $r) {
            $r->get('/dash', fn() => 'ok')->name('dashboard');
        });

        $this->assertNotNull($router->getByName('admin.dashboard'));
    }

    public function test_nested_groups(): void
    {
        $router = $this->router();

        $router->group(['prefix' => 'api', 'middleware' => ['throttle']], function (Router $r) {
            $r->group(['prefix' => 'v1'], function (Router $r2) {
                $r2->get('/posts', fn() => 'ok');
            });
        });

        $this->assertContains('/api/v1/posts', $this->uris($router));
        $this->assertSame(['throttle'], $router->getRoutes()->all()[0]->getMiddleware());
    }

    public function test_resource_routes(): void
    {
        $router = $this->router();
        $router->resource('posts', 'PostController');

        $uris = $this->uris($router);

        $this->assertContains('/posts', $uris);
        $this->assertContains('/posts/create', $uris);
        $this->assertContains('/posts/{id}', $uris);
        $this->assertContains('/posts/{id}/edit', $uris);
        $this->assertNotNull($router->getByName('posts.index'));
        $this->assertNotNull($router->getByName('posts.destroy'));
    }

    /** Only PUT was registered, so every PATCH update request 404'd. */
    public function test_resource_update_accepts_both_put_and_patch(): void
    {
        $router = $this->router();
        $router->resource('posts', 'PostController');

        $update = $router->getByName('posts.update');

        $this->assertNotNull($update);
        $this->assertContains('PUT', $update->getMethods());
        $this->assertContains('PATCH', $update->getMethods());
    }

    /** /posts/create must not be swallowed by /posts/{id}. */
    public function test_resource_create_is_registered_before_show(): void
    {
        $router = $this->router();
        $router->resource('posts', 'PostController');

        $matched = $router->getRoutes()->match($this->makeRequest('GET', '/posts/create'));

        $this->assertSame('/posts/create', $matched?->getUri());
    }

    public function test_resource_only_and_except(): void
    {
        $router = $this->router();
        $router->resource('a', 'C', ['only' => ['index', 'show']]);
        $router->resource('b', 'C', ['except' => ['destroy']]);

        $this->assertNotNull($router->getByName('a.index'));
        $this->assertNull($router->getByName('a.destroy'));
        $this->assertNotNull($router->getByName('b.index'));
        $this->assertNull($router->getByName('b.destroy'));
    }

    // ── URL generation ────────────────────────────────────────────────

    public function test_url_generation(): void
    {
        $router = $this->router();
        $router->get('/users/{id}', fn() => 'ok')->name('users.show');

        $this->assertSame('http://localhost/users/7', $router->url('users.show', ['id' => 7]));
    }

    /** Values were interpolated raw, producing broken URLs for real slugs. */
    public function test_url_parameters_are_encoded(): void
    {
        $router = $this->router();
        $router->get('/search/{term}', fn() => 'ok')->name('search');

        $this->assertSame('http://localhost/search/a%20b%23c', $router->url('search', ['term' => 'a b#c']));
    }

    public function test_extra_parameters_become_a_query_string(): void
    {
        $router = $this->router();
        $router->get('/users/{id}', fn() => 'ok')->name('users.show');

        $this->assertSame(
            'http://localhost/users/7?tab=profile',
            $router->url('users.show', ['id' => 7, 'tab' => 'profile'])
        );
    }

    /** A forgotten argument used to yield a literal "{id}" in the URL. */
    public function test_a_missing_required_parameter_raises(): void
    {
        $router = $this->router();
        $router->get('/users/{id}', fn() => 'ok')->name('users.show');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Missing required parameter/');

        $router->url('users.show');
    }

    public function test_an_unfilled_optional_parameter_is_dropped_with_its_slash(): void
    {
        $router = $this->router();
        $router->get('/posts/{page?}', fn() => 'ok')->name('posts');

        $this->assertSame('http://localhost/posts', $router->url('posts'));
    }

    public function test_an_unknown_route_name_raises(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->router()->url('no.such.route');
    }

    // ── Attribute scanning ────────────────────────────────────────────

    /**
     * The scanner called newInstance() on *every* attribute it found, so one
     * unrelated attribute aborted the whole scan with
     * "Attribute class ... not found". #[Prefix] and #[Middleware] were
     * themselves unreachable because they lived in Route.php.
     */
    public function test_attribute_routes_are_discovered(): void
    {
        $router = $this->router();
        $router->scanController(AttributeController::class);

        $uris = $this->uris($router);

        $this->assertContains('/v2/items', $uris);
        $this->assertContains('/v2/items/{id}', $uris);
        $this->assertNotNull($router->getByName('items.index'));
    }

    public function test_class_and_method_middleware_attributes_combine(): void
    {
        $router = $this->router();
        $router->scanController(AttributeController::class);

        $show = $router->getByName('items.show');

        $this->assertNotNull($show);
        $this->assertContains('auth', $show->getMiddleware(), 'class-level #[Middleware]');
        $this->assertContains('verified', $show->getMiddleware(), 'method-level #[Middleware]');
    }

    public function test_an_unrelated_attribute_does_not_abort_the_scan(): void
    {
        $router = $this->router();
        $router->scanController(ControllerWithForeignAttribute::class);

        $this->assertContains('/still-registered', $this->uris($router));
    }
}

#[Prefix('/v2')]
#[Middleware('auth')]
class AttributeController
{
    #[RouteAttribute('/items', 'GET', 'items.index')]
    public function index(): string { return 'index'; }

    #[RouteAttribute('/items/{id}', ['GET'], 'items.show')]
    #[Middleware('verified')]
    public function show(string $id): string { return "show $id"; }
}

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD)]
class SomeUnrelatedAttribute
{
    public function __construct(public string $note = '') {}
}

#[SomeUnrelatedAttribute('not a routing concern')]
class ControllerWithForeignAttribute
{
    #[SomeUnrelatedAttribute('also not routing')]
    #[RouteAttribute('/still-registered', 'GET')]
    public function handle(): string { return 'ok'; }
}
