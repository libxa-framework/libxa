<?php

declare(strict_types=1);

namespace Libxa\Events;

/**
 * LibxaFrame Event Bus
 *
 * Simple, synchronous event dispatcher.
 * Supports wildcard listeners and queued events.
 *
 * Usage:
 *   EventBus::listen(UserRegistered::class, SendWelcomeEmail::class);
 *   EventBus::dispatch(new UserRegistered($user));
 *
 *   // Closure listener
 *   EventBus::listen('user.*', function ($event) { ... });
 *
 *   // Using the global helper
 *   event(new UserRegistered($user));
 */
class EventBus
{
    protected static array $listeners = [];
    protected static array $wildcards = [];

    // ─────────────────────────────────────────────────────────────────
    //  Registration
    // ─────────────────────────────────────────────────────────────────

    /**
     * Register an event listener.
     *
     * @param string         $event     Fully-qualified event class name, or wildcard (user.*)
     * @param string|callable $listener  Listener class or callable
     */
    public static function listen(string $event, string|callable $listener): void
    {
        if (str_contains($event, '*')) {
            static::$wildcards[$event][] = $listener;
        } else {
            static::$listeners[$event][] = $listener;
        }
    }

    /**
     * Register a one-time listener (auto-removed after first fire).
     */
    public static function once(string $event, callable $listener): void
    {
        $wrapper = null;
        $wrapper = function ($e) use ($listener, $event, &$wrapper) {
            $listener($e);
            static::forget($event, $wrapper);
        };

        static::listen($event, $wrapper);
    }

    /**
     * Remove all listeners for an event.
     */
    public static function forget(string $event, ?callable $listener = null): void
    {
        if ($listener === null) {
            unset(static::$listeners[$event]);
            return;
        }

        static::$listeners[$event] = array_filter(
            static::$listeners[$event] ?? [],
            fn($l) => $l !== $listener
        );
    }

    // ─────────────────────────────────────────────────────────────────
    //  Dispatching
    // ─────────────────────────────────────────────────────────────────

    /**
     * Dispatch an event instance.
     *
     * @return array  Responses from all listeners
     */
    public static function dispatch(object $event): array
    {
        $class     = get_class($event);
        $responses = [];

        // Direct listeners
        $listeners = static::$listeners[$class] ?? [];

        // Wildcard listeners
        foreach (static::$wildcards as $pattern => $wListeners) {
            $regex = '/^' . str_replace('\*', '.*', preg_quote($pattern, '/')) . '$/';
            if (preg_match($regex, $class)) {
                $listeners = array_merge($listeners, $wListeners);
            }
        }

        foreach ($listeners as $listener) {
            $response = static::callListener($listener, $event);
            $responses[] = $response;

            // If listener returns false, stop propagation
            if ($response === false) break;
        }

        // Handle broadcasting
        if ($event instanceof \Libxa\Broadcasting\ShouldBroadcast) {
            $app = \Libxa\Foundation\Application::getInstance();
            if ($app && $app->has('broadcast')) {
                $app->make('broadcast')->event($event);
            }
        }

        return $responses;
    }

    /**
     * Dispatch and collect responses.
     */
    public static function until(object $event): mixed
    {
        foreach ((static::$listeners[get_class($event)] ?? []) as $listener) {
            $result = static::callListener($listener, $event);
            if ($result !== null) return $result;
        }
        return null;
    }

    // ─────────────────────────────────────────────────────────────────
    //  Instance methods (for dependency injection)
    // ─────────────────────────────────────────────────────────────────

    public function on(string $event, string|callable $listener): void
    {
        static::listen($event, $listener);
    }

    public function emit(object $event): array
    {
        return static::dispatch($event);
    }

    public function hasListeners(string $event): bool
    {
        return ! empty(static::$listeners[$event]);
    }

    // ─────────────────────────────────────────────────────────────────
    //  Internals
    // ─────────────────────────────────────────────────────────────────

    protected static function callListener(string|callable $listener, object $event): mixed
    {
        if (is_callable($listener)) {
            return $listener($event);
        }

        // String: 'ListenerClass' or 'ListenerClass@handle'
        [$class, $method] = str_contains($listener, '@')
            ? explode('@', $listener, 2)
            : [$listener, 'handle'];

        $app      = \Libxa\Foundation\Application::getInstance();
        $instance = $app ? $app->make($class) : new $class();

        return $instance->$method($event);
    }
}

/**
 * Base Event class — all events can optionally extend this.
 */
abstract class Event
{
    public readonly \DateTimeImmutable $timestamp;
    public bool $propagationStopped = false;

    public function __construct()
    {
        $this->timestamp = new \DateTimeImmutable();
    }

    public function stopPropagation(): void
    {
        $this->propagationStopped = true;
    }
}
