<?php

declare(strict_types=1);

namespace Libxa\Queue;

/**
 * LibxaFrame Synchronous Queue Driver
 * 
 * This driver executes jobs immediately when they are pushed.
 * It is useful for local development and testing.
 */
class SyncQueue implements Queue
{
    /**
     * Push a new job onto the queue.
     */
    public function push(string|object $job, mixed $data = '', string $queue = null): mixed
    {
        if (is_string($job)) {
            $job = new $job($data);
        }

        // We wrap the job in a try-catch for better debuggability in sync mode
        try {
            if (method_exists($job, 'handle')) {
                $job->handle();
            }
        } catch (\Throwable $e) {
            // Rethrow the exception because in sync mode, errors should be visible immediately
            throw $e;
        }

        return 0; // Return dummy job ID
    }

    /**
     * Push a new job onto the queue after a delay.
     * In sync mode, delay is ignored and job is run immediately.
     */
    public function later(int $delay, string|object $job, mixed $data = '', string $queue = null): mixed
    {
        return $this->push($job, $data, $queue);
    }

    /**
     * Pop the next job off of the queue.
     * Sync mode doesn't store jobs.
     */
    public function pop(string $queue = null): ?Job
    {
        return null;
    }

    /**
     * Get the size of the queue.
     */
    public function size(string $queue = null): int
    {
        return 0;
    }

    /**
     * Delete a reserved job from the queue.
     */
    public function deleteReserved(int $id): void
    {
    }

    /**
     * Release a reserved job back onto the queue.
     */
    public function release(int $id, int $delay = 0): void
    {
    }
}
