<?php

declare(strict_types=1);

namespace Libxa\Container;

use Closure;

/**
 * Contextual Binding Builder
 *
 * Provides a fluent interface for contextual bindings:
 *   $app->when(Controller::class)->needs(Repository::class)->give(fn() => ...);
 */
class ContextualBindingBuilder
{
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
        $this->container->addContextualBinding(
            $this->concrete,
            $this->abstract,
            $implementation
        );
    }

    protected string $abstract = '';
}
