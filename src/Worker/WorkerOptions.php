<?php

declare(strict_types=1);

namespace Luggage\Worker;

final class WorkerOptions
{
    public function __construct(
        public string $queue = 'default',
        public int $sleepSeconds = 1,
        public bool $stopOnEmpty = false,
        public int $memoryLimitMb = 0
    ) {
    }
}

