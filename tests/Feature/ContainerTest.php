<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;
use Libxa\Foundation\Application;

/**
 * Container Test
 * 
 * Verifies that the LibxaFrame core container boots correctly.
 */
class ContainerTest extends TestCase
{
    protected Application $app;

    protected function setUp(): void
    {
        // Mocking a base path for the framework test context
        $this->app = new Application(__DIR__ . '/../../');
    }

    public function test_application_boots_correctly(): void
    {
        $this->assertTrue($this->app->isHttp());
        $this->assertEquals('0.0.1', $this->app->version());
    }

    public function test_container_resolves_itself(): void
    {
        $this->assertInstanceOf(Application::class, $this->app->make('app'));
    }
}
