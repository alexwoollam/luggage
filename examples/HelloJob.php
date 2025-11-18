<?php

declare(strict_types=1);

use Luggage\Contracts\Job;

final class HelloJob implements Job
{
    public function __construct(private array $payload = []) {}

    public static function fromPayload(array $payload): self
    {
        return new self($payload);
    }

    public function handle(): void
    {
        $name = $this->payload['name'] ?? 'there';
        echo "Hello, {$name}!\n";
    }
}
