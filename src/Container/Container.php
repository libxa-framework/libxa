<?php

declare(strict_types=1);

namespace Libxa\Container;

use Closure;
use Psr\Container\ContainerInterface;

/**
 * LibxaFrame Service Container
 *
 * PSR-11 compliant DI container with:
 *  - Singleton & transient bindings
 *  - Contextual bindings (per concrete class)
 *  - Auto-wiring via reflection
 *  - Method injection
 */
class Container implements ContainerInterface
{
    /** Singleton instances */
    protected array $instances = [];

    /** Binding definitions */
    protected array $bindings = [];

    /** Registered aliases */
    protected array $aliases = [];

    /** Contextual bindings: [concrete => [abstract => factory]] */
    protected array $contextualBindings = [];

    /** Stack of concrete classes being built (for contextual resolution) */
    protected array $buildStack = [];

    /** Singleton instance of this container */
    protected static ?self $instance = null;

    // ─────────────────────────────────────────────────────────────────
    //  PSR-11
    // ─────────────────────────────────────────────────────────────────

    public function get(string $id): mixed
    {
        return $this->make($id);
    }

    public function has(string $id): bool
    {
        $id = $this->resolveAlias($id);

        return isset($this->bindings[$id]) || isset($this->instances[$id]);
    }

    /**
     * Follow an alias chain to the underlying abstract name.
     *
     * Guards against self-referential / cyclic aliases (alias('a','b');
     * alias('b','a')) which would otherwise spin forever.
     */
    protected function resolveAlias(string $abstract): string
    {
        $seen = [];

        while (isset($this->aliases[$abstract])) {
            if (isset($seen[$abstract])) {
                throw new \RuntimeException(
                    "Circular alias chain detected while resolving [{$abstract}]."
                );
            }

            $seen[$abstract] = true;
            $abstract        = $this->aliases[$abstract];
        }

        return $abstract;
    }

    // ─────────────────────────────────────────────────────────────────
    //  Binding
    // ─────────────────────────────────────────────────────────────────

    /**
     * Register a transient binding (new instance each time).
     */
    public function bind(string $abstract, Closure|string|null $concrete = null, bool $singleton = false): void
    {
        $abstract   = $this->resolveAlias($abstract);
        $concrete ??= $abstract;

        $this->bindings[$abstract] = [
            'concrete'  => $concrete,
            'singleton' => $singleton,
        ];

        // Clear any cached singleton instance
        unset($this->instances[$abstract]);
    }

    /**
     * Register a singleton binding (same instance returned each time).
     */
    public function singleton(string $abstract, Closure|string|null $concrete = null): void
    {
        $this->bind($abstract, $concrete, singleton: true);
    }

    /**
     * Store a pre-built instance directly.
     */
    public function instance(string $abstract, mixed $instance): mixed
    {
        $this->instances[$this->resolveAlias($abstract)] = $instance;

        return $instance;
    }

    /**
     * Drop a resolved singleton so the next make() rebuilds it.
     * Needed by long-running workers (queue, websocket) that must reset
     * per-request state between jobs instead of leaking it forever.
     */
    public function forgetInstance(string $abstract): void
    {
        unset($this->instances[$this->resolveAlias($abstract)]);
    }

    /**
     * Alias an abstract type to a shorter name.
     */
    public function alias(string $abstract, string $alias): void
    {
        $this->aliases[$alias] = $abstract;
    }

    /**
     * Begin a contextual binding chain.
     * Usage: $this->when(ConcreteClass::class)->needs(Abstract::class)->give(factory)
     */
    public function when(string $concrete): ContextualBindingBuilder
    {
        return new ContextualBindingBuilder($this, $concrete);
    }

    /**
     * Add a contextual binding (called internally by ContextualBindingBuilder).
     */
    public function addContextualBinding(string $concrete, string $abstract, Closure|string $implementation): void
    {
        $this->contextualBindings[$concrete][$abstract] = $implementation;
    }

    // ─────────────────────────────────────────────────────────────────
    //  Resolution
    // ─────────────────────────────────────────────────────────────────

    /**
     * Resolve a class or binding from the container.
     */
    public function make(string $abstract, array $parameters = []): mixed
    {
        // An alias must be collapsed *before* anything else, otherwise
        // `alias(Foo::class, 'foo')` + `singleton(Foo::class)` would rebuild
        // a fresh object every time it is resolved through the short name.
        $abstract = $this->resolveAlias($abstract);

        // Return cached singleton instances
        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        // Find the concrete (what to actually build)
        $concrete = $this->getConcrete($abstract);

        // Build it
        $object = $this->build($concrete, $parameters);

        // Cache if singleton. Never cache when the caller supplied explicit
        // constructor overrides: that object is not the canonical singleton.
        if ($parameters === [] && $this->isSingleton($abstract)) {
            $this->instances[$abstract] = $object;
        }

        return $object;
    }

    protected function getConcrete(string $abstract): Closure|string
    {
        // Contextual binding: check if the current build stack has a match
        if (! empty($this->buildStack)) {
            $buildingClass = end($this->buildStack);

            if (isset($this->contextualBindings[$buildingClass][$abstract])) {
                return $this->contextualBindings[$buildingClass][$abstract];
            }
        }

        if (isset($this->bindings[$abstract])) {
            return $this->bindings[$abstract]['concrete'];
        }

        return $abstract;
    }

    protected function build(Closure|string $concrete, array $parameters = []): mixed
    {
        if ($concrete instanceof Closure) {
            return $concrete($this, $parameters);
        }

        try {
            $reflector = new \ReflectionClass($concrete);
        } catch (\ReflectionException $e) {
            throw new \RuntimeException("Target class [$concrete] does not exist.", 0, $e);
        }

        if (! $reflector->isInstantiable()) {
            throw new \RuntimeException("Target [$concrete] is not instantiable.");
        }

        // Two classes that depend on each other would otherwise recurse until
        // PHP exhausts the stack and the process dies with no usable error.
        if (in_array($concrete, $this->buildStack, true)) {
            throw new \RuntimeException(
                'Circular dependency detected while resolving ['
                . implode(' -> ', [...$this->buildStack, $concrete]) . '].'
            );
        }

        $constructor = $reflector->getConstructor();

        if ($constructor === null) {
            return new $concrete();
        }

        // try/finally: if a dependency throws, the stack must still unwind or
        // every later resolution in this process sees a corrupted build stack
        // (wrong contextual bindings, bogus circular-dependency errors).
        $this->buildStack[] = $concrete;

        try {
            $dependencies = $this->resolveDependencies($constructor->getParameters(), $parameters);
        } finally {
            array_pop($this->buildStack);
        }

        return $reflector->newInstanceArgs($dependencies);
    }

    protected function resolveDependencies(array $parameters, array $overrides = []): array
    {
        $dependencies = [];

        foreach ($parameters as $param) {
            $name = $param->getName();

            // Manual override takes priority. array_key_exists (not isset) so an
            // explicit null override is honoured instead of silently re-resolved.
            if (array_key_exists($name, $overrides)) {
                $dependencies[] = $overrides[$name];
                continue;
            }

            // Variadics swallow the remaining positional overrides. Appending a
            // single null here would pass a bogus argument to the constructor.
            if ($param->isVariadic()) {
                continue;
            }

            $type = $param->getType();

            if ($type instanceof \ReflectionNamedType && ! $type->isBuiltin()) {
                $dependencies[] = $this->resolveClassDependency($param, $type->getName());
                continue;
            }

            if ($param->isDefaultValueAvailable()) {
                $dependencies[] = $param->getDefaultValue();
                continue;
            }

            // hasType() matters: an *untyped* parameter reports allowsNull()
            // === true, so checking allowsNull() alone silently filled every
            // untyped required argument with null.
            if ($param->hasType() && $param->allowsNull()) {
                $dependencies[] = null;
                continue;
            }

            // Previously this injected null, which turned a container
            // misconfiguration into a TypeError deep inside the constructor
            // (or, worse, silently constructed a half-initialised object).
            $where = $this->buildStack ? end($this->buildStack) : 'closure';

            throw new \RuntimeException(
                "Unresolvable dependency: parameter \${$name} in [{$where}] has no type hint and no default value."
            );
        }

        return $dependencies;
    }

    /**
     * Resolve a single class-typed constructor parameter, falling back to the
     * declared default / null only when the class genuinely cannot be built.
     */
    protected function resolveClassDependency(\ReflectionParameter $param, string $className): mixed
    {
        try {
            return $this->make($className);
        } catch (\Throwable $e) {
            // A circular dependency is a programming error, never something to
            // paper over with a default value: it must surface to the developer.
            if ($e instanceof \RuntimeException && str_contains($e->getMessage(), 'Circular dependency')) {
                throw $e;
            }

            if ($param->isDefaultValueAvailable()) {
                return $param->getDefaultValue();
            }

            if ($param->allowsNull()) {
                return null;
            }

            throw $e;
        }
    }

    protected function isSingleton(string $abstract): bool
    {
        return isset($this->bindings[$abstract])
            && $this->bindings[$abstract]['singleton'] === true;
    }

    // ─────────────────────────────────────────────────────────────────
    //  Method Injection
    // ─────────────────────────────────────────────────────────────────

    /**
     * Call a method or closure with automatic injection.
     */
    public function call(array|Closure|string $callback, array $parameters = []): mixed
    {
        if ($callback instanceof Closure) {
            $reflector    = new \ReflectionFunction($callback);
            $dependencies = $this->resolveDependencies($reflector->getParameters(), $parameters);
            return $callback(...$dependencies);
        }

        if (is_array($callback)) {
            [$object, $method] = $callback;
            $reflector         = new \ReflectionMethod($object, $method);
            $dependencies      = $this->resolveDependencies($reflector->getParameters(), $parameters);
            return $reflector->invokeArgs($object, $dependencies);
        }

        if (str_contains($callback, '@')) {
            [$class, $method] = explode('@', $callback, 2);
            $object           = $this->make($class);
            return $this->call([$object, $method], $parameters);
        }

        return $this->make($callback, $parameters);
    }

    // ─────────────────────────────────────────────────────────────────
    //  Static access
    // ─────────────────────────────────────────────────────────────────

    public static function setInstance(self $container): void
    {
        static::$instance = $container;
    }

    public static function getInstance(): ?static
    {
        return static::$instance;
    }
}
