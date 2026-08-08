<?php

declare(strict_types=1);

namespace Tests\Unit;

use Libxa\Container\Container;
use PHPUnit\Framework\TestCase;

/**
 * Regression tests for the service container hardening.
 */
class ContainerTest extends TestCase
{
    private Container $container;

    protected function setUp(): void
    {
        $this->container = new Container();
    }

    public function test_it_resolves_a_plain_class(): void
    {
        $this->assertInstanceOf(NoDeps::class, $this->container->make(NoDeps::class));
    }

    public function test_singleton_returns_the_same_instance(): void
    {
        $this->container->singleton(NoDeps::class);

        $this->assertSame(
            $this->container->make(NoDeps::class),
            $this->container->make(NoDeps::class)
        );
    }

    public function test_bind_returns_a_new_instance_each_time(): void
    {
        $this->container->bind(NoDeps::class);

        $this->assertNotSame(
            $this->container->make(NoDeps::class),
            $this->container->make(NoDeps::class)
        );
    }

    /**
     * A singleton resolved through its alias used to be rebuilt every time,
     * because make() checked the singleton cache before collapsing the alias.
     */
    public function test_a_singleton_resolved_through_an_alias_is_still_shared(): void
    {
        $this->container->singleton(NoDeps::class);
        $this->container->alias(NoDeps::class, 'no-deps');

        $viaClass = $this->container->make(NoDeps::class);
        $viaAlias = $this->container->make('no-deps');

        $this->assertSame($viaClass, $viaAlias);
    }

    /** has() ignored aliases entirely, which breaks PSR-11 expectations. */
    public function test_has_follows_aliases(): void
    {
        $this->container->singleton(NoDeps::class);
        $this->container->alias(NoDeps::class, 'no-deps');

        $this->assertTrue($this->container->has('no-deps'));
        $this->assertFalse($this->container->has('nope'));
    }

    public function test_instance_registered_under_an_alias_is_found_by_the_real_name(): void
    {
        $this->container->alias(NoDeps::class, 'no-deps');
        $object = new NoDeps();

        $this->container->instance('no-deps', $object);

        $this->assertSame($object, $this->container->make(NoDeps::class));
    }

    public function test_a_circular_alias_chain_raises_instead_of_hanging(): void
    {
        $this->container->alias('b', 'a');
        $this->container->alias('a', 'b');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Circular alias/');

        $this->container->has('a');
    }

    /**
     * Two classes depending on each other used to recurse until PHP ran out
     * of stack and the process died with no usable error.
     */
    public function test_a_circular_dependency_raises_instead_of_exhausting_the_stack(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Circular dependency/');

        $this->container->make(CircularA::class);
    }

    /**
     * If a dependency threw, the build stack was never popped, and every later
     * resolution in the process saw a corrupted stack.
     */
    public function test_build_stack_unwinds_when_a_dependency_throws(): void
    {
        try {
            $this->container->make(NeedsExploding::class);
        } catch (\Throwable) {
            // expected
        }

        // A perfectly ordinary resolution must still work afterwards.
        $this->assertInstanceOf(NoDeps::class, $this->container->make(NoDeps::class));
    }

    /**
     * An untyped required parameter used to be filled with null, producing a
     * TypeError deep inside the constructor instead of a container error.
     */
    public function test_an_unresolvable_primitive_dependency_raises_a_clear_error(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Unresolvable dependency/');

        $this->container->make(NeedsPrimitive::class);
    }

    public function test_a_primitive_with_a_default_is_left_alone(): void
    {
        $object = $this->container->make(HasPrimitiveDefault::class);

        $this->assertSame('default', $object->name);
    }

    public function test_explicit_parameters_win_over_autowiring(): void
    {
        $object = $this->container->make(NeedsPrimitive::class, ['name' => 'explicit']);

        $this->assertSame('explicit', $object->name);
    }

    public function test_an_explicit_null_override_is_honoured(): void
    {
        $object = $this->container->make(NullableDep::class, ['dep' => null]);

        $this->assertNull($object->dep);
    }

    public function test_variadic_constructors_do_not_receive_a_bogus_null(): void
    {
        $object = $this->container->make(Variadic::class);

        $this->assertSame([], $object->items);
    }

    public function test_contextual_binding(): void
    {
        $this->container->when(NeedsContract::class)
            ->needs(Contract::class)
            ->give(fn() => new ImplementationB());

        $object = $this->container->make(NeedsContract::class);

        $this->assertInstanceOf(ImplementationB::class, $object->contract);
    }

    public function test_forget_instance_drops_a_resolved_singleton(): void
    {
        $this->container->singleton(NoDeps::class);
        $first = $this->container->make(NoDeps::class);

        $this->container->forgetInstance(NoDeps::class);

        $this->assertNotSame($first, $this->container->make(NoDeps::class));
    }

    public function test_call_injects_closure_dependencies(): void
    {
        $result = $this->container->call(fn(NoDeps $dep) => $dep::class);

        $this->assertSame(NoDeps::class, $result);
    }

    public function test_singletons_are_not_cached_when_parameters_are_supplied(): void
    {
        $this->container->singleton(HasPrimitiveDefault::class);

        $custom = $this->container->make(HasPrimitiveDefault::class, ['name' => 'custom']);
        $plain  = $this->container->make(HasPrimitiveDefault::class);

        $this->assertSame('custom', $custom->name);
        $this->assertSame('default', $plain->name);
    }
}

// ── Fixtures ────────────────────────────────────────────────────────────

class NoDeps {}

class CircularA
{
    public function __construct(public CircularB $b) {}
}

class CircularB
{
    public function __construct(public CircularA $a) {}
}

class Exploding
{
    public function __construct()
    {
        throw new \DomainException('boom');
    }
}

class NeedsExploding
{
    public function __construct(public Exploding $dep) {}
}

class NeedsPrimitive
{
    public function __construct(public $name) {}
}

class HasPrimitiveDefault
{
    public function __construct(public string $name = 'default') {}
}

class NullableDep
{
    public function __construct(public ?NoDeps $dep) {}
}

class Variadic
{
    public array $items;

    public function __construct(NoDeps ...$items)
    {
        $this->items = $items;
    }
}

interface Contract {}
class ImplementationA implements Contract {}
class ImplementationB implements Contract {}

class NeedsContract
{
    public function __construct(public Contract $contract) {}
}
