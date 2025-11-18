<?php

declare(strict_types=1);

namespace Luggage\Queue;

/**
 * Transport container for a job, independent from its implementation.
 * Stores class name, payload, attempt count and availability.
 */
final class JobEnvelope
{
    public function __construct(
        public readonly string $id,
        public readonly string $jobClass,
        public array $payload,
        public int $attempts = 0,
        public int $maxAttempts = 3,
        public \DateTimeImmutable $availableAt = new \DateTimeImmutable('@0')
    ) {
    }

    public static function new(string $jobClass, array $payload, int $maxAttempts = 3, ?\DateTimeImmutable $availableAt = null): self
    {
        $id = bin2hex(random_bytes(8));
        return new self(
            id: $id,
            jobClass: $jobClass,
            payload: $payload,
            attempts: 0,
            maxAttempts: $maxAttempts,
            availableAt: $availableAt ?? new \DateTimeImmutable()
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'jobClass' => $this->jobClass,
            'payload' => $this->payload,
            'attempts' => $this->attempts,
            'maxAttempts' => $this->maxAttempts,
            'availableAt' => $this->availableAt->getTimestamp(),
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: (string) $data['id'],
            jobClass: (string) $data['jobClass'],
            payload: (array) ($data['payload'] ?? []),
            attempts: (int) ($data['attempts'] ?? 0),
            maxAttempts: (int) ($data['maxAttempts'] ?? 3),
            availableAt: new \DateTimeImmutable('@' . (int) ($data['availableAt'] ?? 0))
        );
    }
}

