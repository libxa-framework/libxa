<?php

declare(strict_types=1);

namespace Libxa\Container;

/**
 * Context Graph
 *
 * Tracks the current execution context (http, cli, queue, ws, desktop, test)
 * and exposes it to the container for context-aware binding resolution.
 */
class ContextGraph
{
    public function __construct(protected string $context = 'http') {}

    public function get(): string { return $this->context; }

    public function is(string $context): bool { return $this->context === $context; }
    public function isHttp(): bool    { return $this->context === 'http'; }
    public function isCli(): bool     { return $this->context === 'cli'; }
    public function isQueue(): bool   { return $this->context === 'queue'; }
    public function isWs(): bool      { return $this->context === 'ws'; }
    public function isDesktop(): bool { return $this->context === 'desktop'; }
    public function isTest(): bool    { return $this->context === 'test'; }
}

/**
 * Context-aware binding builder.
 *
 * Usage:
 *   $container->when(SomeController::class)
 *             ->needs(PaymentGateway::class)
 *             ->give(fn() => new StripeGateway());
 */
class ContextualBindingBuilder
{
    public function __construct(
        protected Container $container,
        protected string    $concrete
    ) {}

    public function needs(string $abstract): static
    {
        $this->abstract = $abstract;
        return $this;
    }

    public function give(\Closure|string $implementation): void
    {
        $this->container->addContextualBinding(
            $this->concrete,
            $this->abstract,
            $implementation
        );
    }

    // Contextual + context-aware binding:
    //   ->whenContext('http', fn() => new StripeGateway())
    //   ->whenContext('test', fn() => new FakeGateway())
    public function whenContext(string $context, \Closure $factory): static
    {
        $app = \Libxa\Foundation\Application::getInstance();

        if ($app && $app->context() === $context) {
            $this->container->addContextualBinding(
                $this->concrete,
                $this->abstract,
                $factory
            );
        }

        return $this;
    }

    protected string $abstract = '';
}
