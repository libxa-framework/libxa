<?php

declare(strict_types=1);

namespace Tests\Feature;

use Libxa\Container\ServiceProvider;
use Libxa\Foundation\Application;
use Tests\TestCase;

class ApplicationTest extends TestCase
{
    protected array $env = [
        'ZERO_VALUE'   => '0',
        'EMPTY_VALUE'  => '',
        'TRUE_VALUE'   => 'true',
        'FALSE_VALUE'  => 'false',
        'QUOTED_VALUE' => '"has spaces # and a hash"',
        'COMMENTED'    => 'real-value # trailing comment',
        'FLAG_ONE'     => '1',
        'FLAG_OFF'     => 'off',
    ];

    /**
     * env() was `a ?? b ?: c ?? d`, which PHP groups as (a ?? b) ?: (c ?? d).
     * Any falsy value ("0", "") therefore fell through to the default, so
     * APP_DEBUG=0 behaved exactly like an unset APP_DEBUG.
     */
    public function test_a_falsy_env_value_is_not_replaced_by_the_default(): void
    {
        $this->assertSame('0', Application::env('ZERO_VALUE', 'DEFAULT'));
        $this->assertSame('',  Application::env('EMPTY_VALUE', 'DEFAULT'));
    }

    public function test_missing_keys_return_the_default(): void
    {
        $this->assertSame('DEFAULT', Application::env('DEFINITELY_NOT_SET_' . uniqid(), 'DEFAULT'));
        $this->assertNull(Application::env('ALSO_NOT_SET_' . uniqid()));
    }

    public function test_boolean_literals_are_cast(): void
    {
        $this->assertTrue(Application::env('TRUE_VALUE'));
        $this->assertFalse(Application::env('FALSE_VALUE'));
    }

    public function test_env_bool_accepts_every_common_spelling(): void
    {
        $this->assertTrue(Application::envBool('TRUE_VALUE'));
        $this->assertFalse(Application::envBool('FALSE_VALUE'));
        $this->assertTrue(Application::envBool('FLAG_ONE'), '1 must read as true');
        $this->assertFalse(Application::envBool('ZERO_VALUE'), '0 must read as false');
        $this->assertFalse(Application::envBool('FLAG_OFF'), 'off must read as false');
        $this->assertTrue(Application::envBool('NOT_SET_AT_ALL_' . uniqid(), true), 'default applies');
    }

    /**
     * The old parser ran trim($value, " \"'") over the raw string, mangling
     * quoted values and never stripping inline comments.
     */
    public function test_quoted_values_keep_their_spaces_and_hashes(): void
    {
        $this->assertSame('has spaces # and a hash', Application::env('QUOTED_VALUE'));
    }

    public function test_inline_comments_are_stripped_from_unquoted_values(): void
    {
        $this->assertSame('real-value', Application::env('COMMENTED'));
    }

    public function test_paths_resolve_under_the_base_path(): void
    {
        $this->assertStringStartsWith($this->appPath, $this->app->basePath());
        $this->assertStringContainsString('src' . DIRECTORY_SEPARATOR . 'app', $this->app->appPath());
        $this->assertStringContainsString('src' . DIRECTORY_SEPARATOR . 'config', $this->app->configPath());
        $this->assertStringContainsString('src' . DIRECTORY_SEPARATOR . 'storage', $this->app->storagePath());
    }

    public function test_the_container_resolves_itself(): void
    {
        $this->assertSame($this->app, $this->app->make('app'));
        $this->assertSame($this->app, $this->app->make(Application::class));
    }

    public function test_core_services_are_registered(): void
    {
        $this->app->boot();

        foreach (['config', 'router', 'blade', 'session', 'encrypter', 'cache'] as $service) {
            $this->assertTrue($this->app->has($service), "[{$service}] should be bound");
            $this->assertNotNull($this->app->make($service));
        }
    }

    public function test_config_files_are_loaded(): void
    {
        $this->assertSame('LibxaTest', $this->app->config('app.name'));
        $this->assertTrue($this->app->config('app.debug'));
        $this->assertSame('fallback', $this->app->config('app.nope', 'fallback'));
    }

    /**
     * A provider reachable from several discovery paths used to be registered
     * once per path, duplicating every route and listener it declares.
     */
    public function test_a_provider_is_only_registered_once(): void
    {
        CountingProvider::$registered = 0;
        CountingProvider::$booted     = 0;

        $this->app->register(CountingProvider::class);
        $this->app->register(CountingProvider::class);
        $this->app->register(CountingProvider::class);

        $this->app->boot();
        $this->app->boot();

        $this->assertSame(1, CountingProvider::$registered, 'register() must be idempotent per class');
        $this->assertSame(1, CountingProvider::$booted, 'boot() must run once per provider');
    }

    public function test_booting_is_idempotent(): void
    {
        $this->app->boot();
        $this->app->boot();

        $this->assertTrue($this->app->has('router'));
    }

    /** A provider whose boot() re-enters boot() used to recurse forever. */
    public function test_a_provider_that_re_enters_boot_does_not_recurse(): void
    {
        ReentrantProvider::$booted = 0;

        $this->app->register(ReentrantProvider::class);
        $this->app->boot();

        $this->assertSame(1, ReentrantProvider::$booted);
    }

    public function test_env_helper_matches_the_static_method(): void
    {
        $this->assertSame(Application::env('ZERO_VALUE'), env('ZERO_VALUE'));
    }

    /** app() used to return null, so failures surfaced far from the cause. */
    public function test_resolving_an_unknown_service_raises_a_clear_error(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/does not exist|Unresolvable/');

        $this->app->make('definitely\\not\\a\\Class');
    }
}

class CountingProvider extends ServiceProvider
{
    public static int $registered = 0;
    public static int $booted     = 0;

    public function register(): void
    {
        static::$registered++;
    }

    public function boot(): void
    {
        static::$booted++;
    }
}

class ReentrantProvider extends ServiceProvider
{
    public static int $booted = 0;

    public function register(): void {}

    public function boot(): void
    {
        static::$booted++;
        \Libxa\Foundation\Application::getInstance()?->boot();
    }
}
