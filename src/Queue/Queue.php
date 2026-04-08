<?php

declare(strict_types=1);

namespace Libxa\Queue;

interface Queue
{
    /**
     * Push a new job onto the queue.
     */
    public function push(string|object $job, mixed $data = '', string $queue = null): mixed;

    /**
     * Push a new job onto the queue after a delay.
     */
    public function later(int $delay, string|object $job, mixed $data = '', string $queue = null): mixed;

    /**
     * Pop the next job off of the queue.
     */
    public function pop(string $queue = null): ?Job;

    /**
     * Delete a reserved job from the queue.
     */
    public function deleteReserved(int $id): void;

    /**
     * Release a reserved job back onto the queue.
     */
    public function release(int $id, int $delay = 0): void;

    /**
     * Get the size of the queue.
     */
    public function size(string $queue = null): int;
}
