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
        return isset($this->bindings[$id]) || isset($this->instances[$id]);
    }

    // ─────────────────────────────────────────────────────────────────
    //  Binding
    // ─────────────────────────────────────────────────────────────────

    /**
     * Register a transient binding (new instance each time).
     */
    public function bind(string $abstract, Closure|string|null $concrete = null, bool $singleton = false): void
    {
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
        $this->instances[$abstract] = $instance;

        return $instance;
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
        // Return cached singleton instances
        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        // Find the concrete (what to actually build)
        $concrete = $this->getConcrete($abstract);

        // Build it
        $object = $this->build($concrete, $parameters);

        // Cache if singleton
        if ($this->isSingleton($abstract)) {
            $this->instances[$abstract] = $object;
        }

        return $object;
    }

    protected function getConcrete(string $abstract): Closure|string
    {
        // Resolve alias first
        $abstract = $this->aliases[$abstract] ?? $abstract;

        // Contextual binding — check if the current build stack has a match
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

        $this->buildStack[] = $concrete;

        $constructor = $reflector->getConstructor();

        if ($constructor === null) {
            array_pop($this->buildStack);
            return new $concrete();
        }

        $dependencies = $this->resolveDependencies($constructor->getParameters(), $parameters);

        array_pop($this->buildStack);

        return $reflector->newInstanceArgs($dependencies);
    }

    protected function resolveDependencies(array $parameters, array $overrides = []): array
    {
        $dependencies = [];

        foreach ($parameters as $param) {
            $name = $param->getName();

            // Manual override takes priority
            if (isset($overrides[$name])) {
                $dependencies[] = $overrides[$name];
                continue;
            }

            $type = $param->getType();

            if ($type instanceof \ReflectionNamedType && ! $type->isBuiltin()) {
                $className = $type->getName();
                try {
                    $dependencies[] = $this->make($className);
                } catch (\Throwable $e) {
                    if ($param->isDefaultValueAvailable()) {
                        $dependencies[] = $param->getDefaultValue();
                    } elseif ($param->allowsNull()) {
                        $dependencies[] = null;
                    } else {
                        throw $e;
                    }
                }
            } elseif ($param->isDefaultValueAvailable()) {
                $dependencies[] = $param->getDefaultValue();
            } else {
                $dependencies[] = null;
            }
        }

        return $dependencies;
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
