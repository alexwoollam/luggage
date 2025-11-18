<?php

declare(strict_types=1);

namespace Luggage\Queue;

/**
 * Simple in-memory queue, useful for testing or single-process runs.
 */
final class InMemoryQueueDriver implements QueueDriver
{
    /** @var array<string, list<JobEnvelope>> */
    private array $queues = [];

    public function enqueue(string $queue, JobEnvelope $envelope): void
    {
        $this->queues[$queue] ??= [];
        $this->queues[$queue][] = $envelope;
    }

    public function reserve(string $queue): ?JobEnvelope
    {
        $now = new \DateTimeImmutable();
        $list = $this->queues[$queue] ?? [];
        foreach ($list as $i => $env) {
            if ($env->availableAt <= $now) {
                // Remove and return
                array_splice($this->queues[$queue], $i, 1);
                return $env;
            }
        }
        return null;
    }

    public function ack(string $queue, JobEnvelope $envelope): void
    {
        // No-op: already removed on reserve.
    }

    public function release(string $queue, JobEnvelope $envelope): void
    {
        $this->enqueue($queue, $envelope);
    }

    public function fail(string $queue, JobEnvelope $envelope, \Throwable $error): void
    {
        $dead = $queue . '-dead';
        $this->enqueue($dead, $envelope);
    }
}

