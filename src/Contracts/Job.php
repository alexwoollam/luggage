<?php

declare(strict_types=1);

namespace Luggage\Contracts;

/**
 * Minimal unit of work to be executed by the worker.
 * Implementations should contain only execution logic; construction/serialization
 * should be handled by factories/serializers to keep responsibilities clear.
 */
interface Job
{
    /** Execute the job. Throwing will mark the job as failed for retry logic. */
    public function handle(): void;
}

