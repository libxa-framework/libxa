<?php

declare(strict_types=1);

namespace Libxa\Queue;

abstract class Job
{
    /**
     * The unique ID for the job.
     */
    protected ?int $id = null;

    /**
     * The number of times the job has been attempted.
     */
    protected int $attempts = 0;

    /**
     * Execute the job.
     */
    abstract public function handle(): void;

    /**
     * Set the unique ID for the job.
     */
    public function setId(int $id): static
    {
        $this->id = $id;

        return $this;
    }

    /**
     * Get the unique ID for the job.
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Set the number of attempts for the job.
     */
    public function setAttempts(int $attempts): static
    {
        $this->attempts = $attempts;
        return $this;
    }

    /**
     * Get the number of attempts for the job.
     */
    public function attempts(): int
    {
        return $this->attempts;
    }

    /**
     * Delete the job from the queue.
     */
    public function delete(): void
    {
        // Handled by the worker/queue instance
    }

    /**
     * Release the job back onto the queue.
     */
    public function release(int $delay = 0): void
    {
        // Handled by the worker/queue instance
    }
}
