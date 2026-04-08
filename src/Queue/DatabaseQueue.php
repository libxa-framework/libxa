<?php

declare(strict_types=1);

namespace Libxa\Queue;

use Libxa\Atlas\DB;
use Libxa\Foundation\Application;

class DatabaseQueue implements Queue
{
    /**
     * Create a new database queue instance.
     */
    public function __construct(protected Application $app, protected string $table = 'jobs', protected string $default = 'default')
    {
    }

    /**
     * Push a new job onto the queue.
     */
    public function push(string|object $job, mixed $data = '', string $queue = null): mixed
    {
        return $this->pushToDatabase($queue ?: $this->default, $this->createPayload($job, $data));
    }

    /**
     * Push a new job onto the queue after a delay.
     */
    public function later(int $delay, string|object $job, mixed $data = '', string $queue = null): mixed
    {
        return $this->pushToDatabase($queue ?: $this->default, $this->createPayload($job, $data), $delay);
    }

    /**
     * Pop the next job off of the queue.
     */
    public function pop(string $queue = null): ?Job
    {
        $queue = $queue ?: $this->default;

        return DB::transaction(function () use ($queue) {
            $job = $this->getNextAvailableJob($queue);

            if ($job) {
                $this->markJobAsReserved($job->id);
                return $this->marshalJob($job);
            }

            return null;
        });
    }

    /**
     * Get the size of the queue.
     */
    public function size(string $queue = null): int
    {
        return DB::table($this->table)
            ->where('queue', $queue ?: $this->default)
            ->count();
    }

    /**
     * Delete a reserved job from the queue.
     */
    public function deleteReserved(int $id): void
    {
        DB::table($this->table)->where('id', $id)->deleteRecord();
    }

    /**
     * Release a reserved job back onto the queue.
     */
    public function release(int $id, int $delay = 0): void
    {
        DB::table($this->table)->where('id', $id)->update([
            'reserved_at'  => null,
            'available_at' => time() + $delay,
        ]);
    }

    /**
     * Get the next available job for the queue.
     */
    protected function getNextAvailableJob(string $queue): mixed
    {
        return DB::table($this->table)
            ->where('queue', $queue)
            ->where('available_at', '<=', time())
            ->whereNull('reserved_at')
            ->orderBy('id', 'asc')
            ->first();
    }

    /**
     * Mark the given job ID as reserved.
     */
    protected function markJobAsReserved(int $id): void
    {
        DB::table($this->table)->where('id', $id)->update([
            'reserved_at' => time(),
            'attempts'    => DB::raw('attempts + 1'),
        ]);
    }

    /**
     * Marshal the reserved job into a Job instance.
     */
    protected function marshalJob(mixed $job): ?Job
    {
        $payload = json_decode($job->payload, true);
        $jobInstance = unserialize($payload['job']);

        if ($jobInstance instanceof Job) {
            $jobInstance->setId((int) $job->id);
            $jobInstance->setAttempts((int) $job->attempts);
        }

        return $jobInstance;
    }

    /**
     * Push a raw payload to the database.
     */
    protected function pushToDatabase(string $queue, string $payload, int $delay = 0): int
    {
        return (int) DB::table($this->table)->insertGetId([
            'queue'        => $queue,
            'payload'      => $payload,
            'attempts'     => 0,
            'reserved_at'  => null,
            'available_at' => time() + $delay,
            'created_at'   => time(),
        ]);
    }

    /**
     * Create a payload string from the job and data.
     */
    protected function createPayload(string|object $job, mixed $data = ''): string
    {
        if (is_string($job)) {
            $job = new $job($data);
        }

        return json_encode([
            'job' => serialize($job),
            'data' => $data,
            'id' => bin2hex(random_bytes(16)),
        ]);
    }
}

