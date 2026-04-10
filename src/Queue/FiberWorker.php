<?php

namespace Libxa\Queue;

use Libxa\Async\Parallel;
use Fiber;

class FiberWorker
{
    protected array $batch = [];
    protected int $maxConcurrency = 5;

    /**
     * Set the batch of jobs to process concurrently.
     * 
     * @param array $jobs
     * @return static
     */
    public function batch(array $jobs): static
    {
        $this->batch = $jobs;
        return $this;
    }

    /**
     * Set maximum concurrency level for the fiber swarm.
     * 
     * @param int $concurrency
     * @return static
     */
    public function maxConcurrency(int $concurrency): static
    {
        $this->maxConcurrency = $concurrency;
        return $this;
    }

    /**
     * Dispatch the batch asynchronously using Fibers.
     * 
     * @return array Results of the dispatched jobs
     */
    public function dispatch(): array
    {
        $chunks = array_chunk($this->batch, $this->maxConcurrency, true);
        $results = [];

        foreach ($chunks as $chunk) {
            // Convert Queue job instances or closures to basic closures for the Parallel runner
            $tasks = [];
            foreach ($chunk as $key => $job) {
                $tasks[$key] = function () use ($job) {
                    if (is_callable($job)) {
                        return call_user_func($job);
                    }
                    if ($job instanceof Job) {
                        return $job->handle();
                    }
                    if (method_exists($job, 'handle')) {
                        return $job->handle();
                    }
                    throw new \InvalidArgumentException("Queue Job cannot be resolved to a callable.");
                };
            }

            // Run the current concurrent chunk using the Native PHP Fiber engine
            $chunkResults = Parallel::run($tasks);
            
            foreach ($chunkResults as $key => $result) {
                $results[$key] = $result;
            }
        }

        return $results;
    }
}
