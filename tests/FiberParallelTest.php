<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use Libxa\Async\Parallel;

class FiberParallelTest extends TestCase
{
    public function test_concurrent_execution_returns_mapped_results()
    {
        $startTime = microtime(true);

        $results = Parallel::run([
            'task_a' => function () {
                usleep(50000); // 50ms
                return 'A';
            },
            'task_b' => function () {
                usleep(25000); // 25ms
                return 'B';
            },
            'task_c' => function () {
                usleep(60000); // 60ms
                return 'C';
            }
        ]);

        $endTime = microtime(true);
        $duration = $endTime - $startTime;

        $this->assertEquals(['task_a' => 'A', 'task_b' => 'B', 'task_c' => 'C'], $results);
        // The total time should be roughly equal to the longest task (60ms), definitely less than serial execution (135ms)
        $this->assertTrue($duration < 0.135, "Tasks did not run concurrently. Took {$duration}s");
    }
}
