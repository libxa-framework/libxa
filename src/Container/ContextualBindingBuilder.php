<?php

declare(strict_types=1);

namespace Libxa\Container;

use Closure;

/**
 * Contextual Binding Builder
 *
 * Provides a fluent interface for contextual bindings:
 *   $app->when(Controller::class)->needs(Repository::class)->give(fn() => ...);
 *
 * whenContext() additionally scopes a binding to a runtime context:
 *   $app->when(Checkout::class)
 *       ->needs(Gateway::class)
 *       ->whenContext('testing', fn() => new FakeGateway())
 *       ->whenContext('http',    fn() => new StripeGateway());
 */
class ContextualBindingBuilder
{
    protected string $abstract = '';

    public function __construct(
        protected Container $container,
        protected string    $concrete,
    ) {}

    public function needs(string $abstract): static
    {
        $this->abstract = $abstract;
        return $this;
    }

    public function give(Closure|string $implementation): void
    {
        $this->assertNeedsWasCalled();

        $this->container->addContextualBinding(
            $this->concrete,
            $this->abstract,
            $implementation
        );
    }

    /**
     * Register the binding only when the application is running in the
     * given context (http, cli, queue, ws, desktop, test).
     */
    public function whenContext(string $context, Closure $factory): static
    {
        $this->assertNeedsWasCalled();

        $app = \Libxa\Foundation\Application::getInstance();

        if ($app !== null && $app->context() === $context) {
            $this->container->addContextualBinding(
                $this->concrete,
                $this->abstract,
                $factory
            );
        }

        return $this;
    }

    /**
     * give() used to register a binding for the empty-string abstract when
     * needs() had been forgotten — a silent no-op that was very hard to spot.
     */
    protected function assertNeedsWasCalled(): void
    {
        if ($this->abstract === '') {
            throw new \LogicException(
                "Call needs() before give()/whenContext() when building a contextual binding for [{$this->concrete}]."
            );
        }
    }
}
