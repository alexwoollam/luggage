<?php

declare(strict_types=1);

namespace Luggage\Contracts;

use Luggage\Retry\BackoffStrategy;

/**
 * Marker for jobs that can customize retry behavior.
 */
interface RetryableJob extends Job
{
    public function getMaxAttempts(): int;

    public function getBackoffStrategy(): BackoffStrategy;
}

