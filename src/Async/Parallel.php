<?php

namespace Libxa\Async;

use Fiber;

class Parallel
{
    /**
     * Run an array of closures concurrently using PHP Fibers.
     * 
     * @param array<string, \Closure> $tasks
     * @return array
     */
    public static function run(array $tasks): array
    {
        $fibers = [];
        $results = [];

        // Initialize fibers
        foreach ($tasks as $key => $callable) {
            $fibers[$key] = new Fiber($callable);
        }

        // Start all fibers
        foreach ($fibers as $key => $fiber) {
            $fiber->start();
        }

        // Event loop simulator: tick until all fibers finish
        $completed = 0;
        $total = count($fibers);

        while ($completed < $total) {
            $completed = 0;

            foreach ($fibers as $key => $fiber) {
                if ($fiber->isTerminated()) {
                    $results[$key] = $fiber->getReturn();
                    $completed++;
                } elseif ($fiber->isSuspended()) {
                    $fiber->resume();
                }
            }

            // Small delay to prevent CPU spinning if we're genuinely waiting for non-blocking I/O
            if ($completed < $total) {
                // If there are native async I/O handlers (like stream_select), they would be integrated here
                usleep(100); 
            }
        }

        return $results;
    }
}
