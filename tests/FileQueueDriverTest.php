<?php

declare(strict_types=1);

use Luggage\Contracts\Job;
use Luggage\Queue\FileQueueDriver;
use Luggage\Queue\JobEnvelope;
use PHPUnit\Framework\TestCase;

final class FileQueueDriverTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'luggage_test_' . bin2hex(random_bytes(4));
        @mkdir($this->dir, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->dir);
    }

    public function testEnqueueReserveAck(): void
    {
        $driver = new FileQueueDriver($this->dir);

        $job = new class ([]) implements Job {
            public function __construct(private array $payload) {}
            public function handle(): void {}
        };
        $jobClass = get_class($job);

        $env = JobEnvelope::new($jobClass, ['x' => 1]);
        $driver->enqueue('default', $env);

        $reserved = $driver->reserve('default');
        $this->assertNotNull($reserved);
        $this->assertSame($env->id, $reserved->id);

        // After ack, there should be no reserved/ready files for this id
        $driver->ack('default', $reserved);
        $files = glob($this->dir . '/default/*' . $env->id . '*') ?: [];
        $this->assertCount(0, $files);
    }

    public function testReleaseWithDelayAndRequeue(): void
    {
        $driver = new FileQueueDriver($this->dir);

        $job = new class ([]) implements Job {
            public function __construct(private array $payload) {}
            public function handle(): void {}
        };
        $jobClass = get_class($job);

        $env = JobEnvelope::new($jobClass, []);
        $driver->enqueue('default', $env);
        $env = $driver->reserve('default');
        $this->assertNotNull($env);

        // Set a future availability and release: should not be reservable now
        $env->availableAt = (new DateTimeImmutable())->modify('+10 seconds');
        $driver->release('default', $env);
        $this->assertNull($driver->reserve('default'));

        // Make it available and release again: should be reservable now
        $env->availableAt = new DateTimeImmutable();
        $driver->release('default', $env);
        $this->assertNotNull($driver->reserve('default'));
    }

    private function rrmdir(string $dir): void
    {
        if (!is_dir($dir)) return;
        $items = array_diff(scandir($dir) ?: [], ['.', '..']);
        foreach ($items as $item) {
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                $this->rrmdir($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }
}

