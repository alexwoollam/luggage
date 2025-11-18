<?php

declare(strict_types=1);

namespace Luggage\Logging;

interface Logger
{
    public function log(string $level, string $message, array $context = []): void;

    public function info(string $message, array $context = []): void;
    public function error(string $message, array $context = []): void;
    public function debug(string $message, array $context = []): void;
}

