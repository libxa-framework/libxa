<?php

declare(strict_types=1);

namespace Tests\Unit;

use Libxa\Broadcasting\BroadcastManager;
use Libxa\Broadcasting\Broadcaster;
use Libxa\Foundation\Application;
use PHPUnit\Framework\TestCase;

/**
 * Packages can register a broadcast driver.
 *
 * A driver had to be a `create<Name>Driver` method on BroadcastManager, so the
 * only way to add one was to edit the framework — which made broadcasting the
 * one subsystem a package could not extend, and a WebSocket package the
 * obvious thing that could not be written.
 */
final class BroadcastManagerExtendTest extends TestCase
{
    private function manager(): BroadcastManager
    {
        return new BroadcastManager(new Application(__DIR__));
    }

    private function broadcaster(string $tag): Broadcaster
    {
        return new class ($tag) implements Broadcaster {
            public array $sent = [];

            public function __construct(public readonly string $tag)
            {
            }

            public function broadcast(array $channels, string $event, array $payload): void
            {
                $this->sent[] = [$channels, $event, $payload];
            }
        };
    }

    public function test_a_registered_driver_resolves(): void
    {
        $manager = $this->manager();

        $manager->extend('socket', fn (): Broadcaster => $this->broadcaster('socket'));

        self::assertSame('socket', $manager->connection('socket')->tag);
    }

    public function test_the_factory_receives_the_connection_config(): void
    {
        $manager = $this->manager();

        $seen = null;

        $manager->extend('socket', function (array $config) use (&$seen): Broadcaster {
            $seen = $config;

            return $this->broadcaster('socket');
        });

        $manager->connection('socket');

        self::assertIsArray($seen);
    }

    public function test_an_unknown_driver_says_what_is_available(): void
    {
        // "Not supported" without naming the alternatives turns a typo into a
        // hunt through the framework.
        $manager = $this->manager();
        $manager->extend('socket', fn (): Broadcaster => $this->broadcaster('socket'));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/socket/');

        $manager->connection('carrier-pigeon');
    }

    public function test_available_drivers_includes_the_built_ins_and_the_registered(): void
    {
        $manager = $this->manager();
        $manager->extend('socket', fn (): Broadcaster => $this->broadcaster('socket'));

        $drivers = $manager->availableDrivers();

        self::assertContains('log', $drivers);
        self::assertContains('socket', $drivers);
    }

    public function test_a_registered_driver_replaces_a_built_in_of_the_same_name(): void
    {
        // So an application can swap the shipped implementation without the
        // framework having to know it did.
        $manager = $this->manager();

        $manager->extend('log', fn (): Broadcaster => $this->broadcaster('mine'));

        self::assertSame('mine', $manager->connection('log')->tag);
    }

    public function test_registering_after_a_resolve_takes_effect(): void
    {
        // Providers boot in an order nobody controls, so an instance cached
        // before the registration must not silently win.
        $manager = $this->manager();

        $manager->extend('socket', fn (): Broadcaster => $this->broadcaster('first'));
        $manager->connection('socket');

        $manager->extend('socket', fn (): Broadcaster => $this->broadcaster('second'));

        self::assertSame('second', $manager->connection('socket')->tag);
    }
}
