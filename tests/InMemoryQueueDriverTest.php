<?php

declare(strict_types=1);

use Luggage\Contracts\Job;
use Luggage\Queue\InMemoryQueueDriver;
use Luggage\Queue\JobEnvelope;
use PHPUnit\Framework\TestCase;

final class InMemoryQueueDriverTest extends TestCase
{
    public function testEnqueueReserveAndAck(): void
    {
        $driver = new InMemoryQueueDriver();

        $job = new class ([]) implements Job {
            public function __construct(private array $payload) {}
            public function handle(): void {}
        };
        $jobClass = get_class($job);

        $env = JobEnvelope::new($jobClass, ['foo' => 'bar']);
        $driver->enqueue('default', $env);

        $reserved = $driver->reserve('default');
        $this->assertNotNull($reserved);
        $this->assertSame($env->id, $reserved->id);

        $driver->ack('default', $reserved);
        $this->assertNull($driver->reserve('default'));
    }

    public function testReserveRespectsAvailableAt(): void
    {
        $driver = new InMemoryQueueDriver();

        $job = new class ([]) implements Job {
            public function __construct(private array $payload) {}
            public function handle(): void {}
        };
        $jobClass = get_class($job);

        $future = (new DateTimeImmutable())->modify('+10 seconds');
        $env = JobEnvelope::new($jobClass, [], 3, $future);
        $driver->enqueue('default', $env);

        $this->assertNull($driver->reserve('default'), 'Should not reserve before availableAt');

        // Make it available now; same object instance is in the queue.
        $env->availableAt = new DateTimeImmutable();
        $this->assertNotNull($driver->reserve('default'));
    }
}

