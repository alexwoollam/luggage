<?php

declare(strict_types=1);

namespace Luggage\Queue;

interface QueueDriver
{
    public function enqueue(string $queue, JobEnvelope $envelope): void;

    /** Reserve a job for processing. Returns null if none available. */
    public function reserve(string $queue): ?JobEnvelope;

    /** Acknowledge successful processing; remove from the queue. */
    public function ack(string $queue, JobEnvelope $envelope): void;

    /** Release back to the queue for retry (envelope already updated). */
    public function release(string $queue, JobEnvelope $envelope): void;

    /** Move to dead-letter storage or equivalent. */
    public function fail(string $queue, JobEnvelope $envelope, \Throwable $error): void;
}

