<?php

declare(strict_types=1);

namespace Libxa\Container;

/**
 * Context Graph
 *
 * Tracks the current execution context (http, cli, queue, ws, desktop, test)
 * and exposes it to the container for context-aware binding resolution.
 *
 * Note: this file also used to declare a second copy of
 * ContextualBindingBuilder, which already has its own file. Loading both: as
 * happens the moment an application uses ->when() after the Application
 * constructor has instantiated a ContextGraph: is a hard fatal:
 * "Cannot redeclare class Libxa\Container\ContextualBindingBuilder".
 * The whenContext() method that copy carried has been merged into the real
 * ContextualBindingBuilder.
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
