<?php

declare(strict_types=1);

namespace Luggage\Logging;

final class StdoutLogger implements Logger
{
    public function __construct(private bool $debug = false)
    {
    }

    public function log(string $level, string $message, array $context = []): void
    {
        $ts = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $ctx = $context ? ' ' . json_encode($context, JSON_UNESCAPED_SLASHES) : '';
        fwrite(STDOUT, sprintf("[%s] %s: %s%s\n", $ts, strtoupper($level), $message, $ctx));
    }

    public function info(string $message, array $context = []): void
    {
        $this->log('info', $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->log('error', $message, $context);
    }

    public function debug(string $message, array $context = []): void
    {
        if ($this->debug) {
            $this->log('debug', $message, $context);
        }
    }
}

