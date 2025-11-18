<?php

declare(strict_types=1);

use Luggage\Contracts\Job;
use Luggage\Contracts\RetryableJob;
use Luggage\Queue\InMemoryQueueDriver;
use Luggage\Queue\JobEnvelope;
use Luggage\Retry\BackoffStrategy;
use Luggage\Worker\Worker;
use Luggage\Worker\WorkerOptions;
use PHPUnit\Framework\TestCase;

final class WorkerTest extends TestCase
{
    public function testRunOnceProcessesJob(): void
    {
        $driver = new InMemoryQueueDriver();
        $worker = new Worker($driver);

        $job = new class ([]) implements Job {
            public static int $count = 0;
            public function __construct(private array $payload) {}
            public function handle(): void { self::$count++; }
        };
        $jobClass = get_class($job);

        $env = JobEnvelope::new($jobClass, []);
        $driver->enqueue('default', $env);

        $worker->runOnce(new WorkerOptions(queue: 'default'));
        $this->assertSame(1, $job::$count);
        $this->assertNull($driver->reserve('default'));
    }

    public function testRetryAndDeadLetter(): void
    {
        $driver = new InMemoryQueueDriver();
        $worker = new Worker($driver);

        $job = new class ([]) implements RetryableJob {
            public function __construct(private array $payload) {}
            public function handle(): void { throw new RuntimeException('nope'); }
            public function getMaxAttempts(): int { return 2; }
            public function getBackoffStrategy(): BackoffStrategy {
                return new class implements BackoffStrategy {
                    public function nextDelaySeconds(int $attempt, ?Throwable $error = null): int { return 0; }
                };
            }
        };
        $jobClass = get_class($job);

        $env = JobEnvelope::new($jobClass, []);
        $driver->enqueue('default', $env);

        // First attempt: fails and is released immediately
        $worker->runOnce(new WorkerOptions(queue: 'default'));
        $this->assertNull($driver->reserve('default-dead'));

        // Second attempt: fails and moves to dead-letter
        $worker->runOnce(new WorkerOptions(queue: 'default'));
        $dead = $driver->reserve('default-dead');
        $this->assertNotNull($dead, 'Job should be in dead-letter queue');
        $this->assertSame($env->id, $dead->id);
    }
}

