<?php

declare(strict_types=1);

namespace Libxa\Events;

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
