<?php

declare(strict_types=1);

namespace Tests\Unit;

use Libxa\Blade\BladeEngine;
use Libxa\Container\ServiceProvider;
use Libxa\Foundation\Application;
use PHPUnit\Framework\TestCase;

/**
 * A published view actually overrides the package's copy.
 *
 * Packages publish their views to src/resources/views/vendor/<namespace> so an
 * application can customise them. Only the package directory was registered,
 * so `vendor:publish --tag=<pkg>-views` wrote a complete copy of every view
 * and the engine never looked at any of it. Editing one changed nothing, with
 * no error and no hint as to why.
 */
final class PublishedViewOverrideTest extends TestCase
{
    private string $base;

    protected function setUp(): void
    {
        $this->base = sys_get_temp_dir() . '/libxa-views-' . bin2hex(random_bytes(6));

        mkdir($this->base . '/src/resources/views/vendor/pkg', 0755, true);
        mkdir($this->base . '/package-views', 0755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->base);
    }

    private function removeDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        foreach (scandir($directory) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $directory . '/' . $entry;

            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }

        rmdir($directory);
    }

    /** A provider that registers views the way every package does. */
    private function register(Application $app, string $packagePath): void
    {
        $provider = new class ($app) extends ServiceProvider {
            public string $packagePath = '';

            public function boot(): void
            {
                $this->loadViewsFrom($this->packagePath, 'pkg');
            }
        };

        $provider->packagePath = $packagePath;
        $provider->boot();
    }

    private function application(): Application
    {
        $app = new Application($this->base);
        $app->instance('blade', new BladeEngine($this->base . '/src/resources/views', $this->base . '/storage/framework/views'));

        return $app;
    }

    public function test_the_published_copy_is_searched_before_the_package(): void
    {
        file_put_contents($this->base . '/package-views/greeting.blade.php', 'from the package');
        file_put_contents($this->base . '/src/resources/views/vendor/pkg/greeting.blade.php', 'from the application');

        $app = $this->application();
        $this->register($app, $this->base . '/package-views');

        self::assertStringContainsString(
            'vendor',
            $app->make('blade')->resolvePath('pkg::greeting'),
        );
    }

    public function test_a_view_the_application_did_not_publish_still_resolves(): void
    {
        // Publishing is partial in practice: people keep the one view they
        // changed and delete the rest. The package must still answer for
        // everything else.
        file_put_contents($this->base . '/package-views/untouched.blade.php', 'from the package');

        $app = $this->application();
        $this->register($app, $this->base . '/package-views');

        self::assertStringContainsString(
            'package-views',
            $app->make('blade')->resolvePath('pkg::untouched'),
        );
    }

    public function test_nothing_breaks_when_the_application_published_nothing(): void
    {
        $this->removeDirectory($this->base . '/src/resources/views/vendor/pkg');

        file_put_contents($this->base . '/package-views/greeting.blade.php', 'from the package');

        $app = $this->application();
        $this->register($app, $this->base . '/package-views');

        self::assertStringContainsString(
            'package-views',
            $app->make('blade')->resolvePath('pkg::greeting'),
        );
    }
}
