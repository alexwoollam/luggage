<?php

declare(strict_types=1);

namespace Luggage\Retry;

final class ExponentialBackoff implements BackoffStrategy
{
    public function __construct(
        private int $baseSeconds = 1,
        private float $factor = 2.0,
        private int $maxSeconds = 60
    ) {
        $this->baseSeconds = max(0, $this->baseSeconds);
        $this->maxSeconds = max(0, $this->maxSeconds);
        $this->factor = max(1.0, $this->factor);
    }

    public function nextDelaySeconds(int $attempt, ?\Throwable $error = null): int
    {
        if ($attempt <= 0) {
            $attempt = 1;
        }
        $delay = (int) round($this->baseSeconds * ($this->factor ** ($attempt - 1)));
        return min($delay, $this->maxSeconds);
    }
}

