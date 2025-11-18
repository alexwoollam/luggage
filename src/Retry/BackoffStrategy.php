<?php

declare(strict_types=1);

namespace Luggage\Retry;

interface BackoffStrategy
{
    /**
     * Return the number of seconds to delay before the next attempt.
     * $attempt is the current attempt number (starting at 1 for the first failure).
     */
    public function nextDelaySeconds(int $attempt, ?\Throwable $error = null): int;
}

