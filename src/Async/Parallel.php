<?php

declare(strict_types=1);

namespace Libxa\Async;

use Fiber;
use Throwable;

/**
 * Cooperative concurrency over PHP Fibers.
 *
 * Important: fibers are *cooperative*, not pre-emptive. A task only overlaps
 * with the others at the points where it calls Fiber::suspend(). A task that
 * blocks (usleep, a synchronous PDO query, file_get_contents on a socket) runs
 * to completion before any other task advances — that is a property of fibers,
 * not a bug here.
 *
 * Stability notes:
 *  - A task that throws used to abort run() from inside start()/resume(),
 *    leaving every other fiber suspended and its result lost. Exceptions are
 *    captured per task now and rethrown together once the batch drains.
 *  - The scheduler loop had no way out if a fiber ended up neither terminated
 *    nor suspended (for example one whose start() threw): $completed stayed
 *    below $total forever and the request hung until the PHP time limit
 *    killed it. The loop now tracks fiber state explicitly.
 */
class Parallel
{
    /**
     * Run an array of closures concurrently using PHP Fibers.
     *
     * @param  array<string, \Closure>  $tasks
     * @return array<string, mixed> results keyed exactly like $tasks
     *
     * @throws ParallelException if any task threw.
     */
    public static function run(array $tasks): array
    {
        [$results, $errors] = static::settle($tasks);

        if ($errors !== []) {
            throw new ParallelException($errors);
        }

        return $results;
    }

    /**
     * Like run(), but never throws: returns [results, errors] so the caller
     * can decide what a partial failure means.
     *
     * @param  array<string, \Closure>  $tasks
     * @return array{0: array<string, mixed>, 1: array<string, Throwable>}
     */
    public static function settle(array $tasks): array
    {
        /** @var array<string, Fiber> $pending */
        $pending = [];
        $results = [];
        $errors  = [];

        foreach ($tasks as $key => $callable) {
            $fiber = new Fiber($callable);

            try {
                $fiber->start();
            } catch (Throwable $e) {
                $errors[$key] = $e;
                continue;
            }

            if ($fiber->isTerminated()) {
                $results[$key] = $fiber->getReturn();
                continue;
            }

            $pending[$key] = $fiber;
        }

        while ($pending !== []) {
            $advanced = false;

            foreach ($pending as $key => $fiber) {
                if ($fiber->isTerminated()) {
                    $results[$key] = $fiber->getReturn();
                    unset($pending[$key]);
                    $advanced = true;
                    continue;
                }

                if (! $fiber->isSuspended()) {
                    // Not terminated and not resumable — nothing this loop can
                    // do with it. Dropping it is what stops the hang.
                    $errors[$key] = new \RuntimeException(
                        "Fiber [{$key}] is stuck in a non-resumable state."
                    );
                    unset($pending[$key]);
                    $advanced = true;
                    continue;
                }

                try {
                    $fiber->resume();
                } catch (Throwable $e) {
                    $errors[$key] = $e;
                    unset($pending[$key]);
                }

                $advanced = true;
            }

            if (! $advanced) {
                break;
            }

            if ($pending !== []) {
                usleep(100); // yield the CPU while waiting on non-blocking I/O
            }
        }

        // Preserve the caller's key order.
        $ordered = [];
        foreach (array_keys($tasks) as $key) {
            if (array_key_exists($key, $results)) {
                $ordered[$key] = $results[$key];
            }
        }

        return [$ordered, $errors];
    }
}
