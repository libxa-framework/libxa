<?php

declare(strict_types=1);

namespace Tests\Unit;

use Libxa\Foundation\Application;
use Libxa\Foundation\ConsoleKernel;
use Libxa\Foundation\HttpKernel;
use PHPUnit\Framework\TestCase;

/**
 * The kernel the container hands out is the kernel that runs the request.
 *
 * HttpKernel::pushMiddleware() is documented as the way packages and
 * providers add global middleware. Resolving an unbound class builds a fresh
 * object each time, so a provider calling
 *
 *     $app->make(HttpKernel::class)->pushMiddleware(Foo::class);
 *
 * pushed onto a kernel nothing ever used. The middleware never ran, no error
 * was raised, and the stack the request went through looked completely
 * ordinary.
 */
final class KernelIsSharedTest extends TestCase
{
    private function app(): Application
    {
        return new Application(__DIR__);
    }

    public function test_the_http_kernel_is_shared(): void
    {
        $app = $this->app();

        self::assertSame($app->make(HttpKernel::class), $app->make(HttpKernel::class));
    }

    public function test_the_console_kernel_is_shared(): void
    {
        $app = $this->app();

        self::assertSame($app->make(ConsoleKernel::class), $app->make(ConsoleKernel::class));
    }

    public function test_middleware_pushed_after_the_kernel_was_resolved_is_visible(): void
    {
        // The exact sequence public/index.php produces: the kernel is
        // resolved, then handle() boots the providers, and a provider pushes
        // middleware from boot().
        $app = $this->app();

        $kernel = $app->make(HttpKernel::class);

        $app->make(HttpKernel::class)->pushMiddleware('App\\Http\\Middleware\\Example');

        self::assertContains('App\\Http\\Middleware\\Example', $kernel->getMiddleware());
    }

    public function test_the_kernel_is_reachable_through_has(): void
    {
        // Providers guard on has() before pushing. It returned false for the
        // kernel, so the careful spelling silently did nothing at all.
        self::assertTrue($this->app()->has(HttpKernel::class));
    }

    public function test_pushing_the_same_middleware_twice_does_not_duplicate_it(): void
    {
        $app = $this->app();
        $kernel = $app->make(HttpKernel::class);

        $kernel->pushMiddleware('App\\Http\\Middleware\\Example');
        $kernel->pushMiddleware('App\\Http\\Middleware\\Example');

        self::assertSame(
            1,
            count(array_keys($kernel->getMiddleware(), 'App\\Http\\Middleware\\Example', true)),
        );
    }
}
