<?php

declare(strict_types=1);

namespace Luggage\Worker;

use Luggage\Contracts\Job;
use Luggage\Contracts\RetryableJob;
use Luggage\Logging\Logger;
use Luggage\Logging\StdoutLogger;
use Luggage\Queue\JobEnvelope;
use Luggage\Queue\QueueDriver;
use Luggage\Retry\ExponentialBackoff;

final class Worker
{
    public function __construct(
        private QueueDriver $driver,
        private JobFactory $factory = new JobFactory(),
        private Logger $logger = new StdoutLogger()
    ) {
    }

    public function run(WorkerOptions $options): void
    {
        $queue = $options->queue;
        $this->logger->info('Worker started', ['queue' => $queue]);
        $idle = 0;
        while (true) {
            if ($options->memoryLimitMb > 0 && $this->overMemory($options->memoryLimitMb)) {
                $this->logger->info('Memory limit reached, stopping');
                break;
            }

            $envelope = $this->driver->reserve($queue);
            if ($envelope === null) {
                $idle++;
                if ($options->stopOnEmpty) {
                    $this->logger->info('No jobs, exiting');
                    break;
                }
                sleep($options->sleepSeconds);
                continue;
            }
            $idle = 0;
            $this->process($queue, $envelope);
        }
        $this->logger->info('Worker stopped', ['queue' => $queue]);
    }

    public function runOnce(WorkerOptions $options): void
    {
        $envelope = $this->driver->reserve($options->queue);
        if ($envelope) {
            $this->process($options->queue, $envelope);
        }
    }

    private function process(string $queue, JobEnvelope $envelope): void
    {
        $this->logger->debug('Processing job', ['id' => $envelope->id, 'class' => $envelope->jobClass, 'attempts' => $envelope->attempts]);
        try {
            $job = $this->factory->make($envelope->jobClass, $envelope->payload);
            $this->applyRetryHints($job, $envelope);

            $job->handle();

            $this->driver->ack($queue, $envelope);
            $this->logger->info('Job done', ['id' => $envelope->id]);
        } catch (\Throwable $e) {
            $envelope->attempts++;
            $this->logger->error('Job failed', ['id' => $envelope->id, 'error' => $e->getMessage()]);

            if ($envelope->attempts >= $envelope->maxAttempts) {
                $this->driver->fail($queue, $envelope, $e);
                $this->logger->error('Job moved to dead-letter', ['id' => $envelope->id]);
                return;
            }

            $backoff = new ExponentialBackoff();
            if ($job instanceof RetryableJob) {
                $backoff = $job->getBackoffStrategy();
            }
            $delay = $backoff->nextDelaySeconds($envelope->attempts, $e);
            $envelope->availableAt = (new \DateTimeImmutable())->modify('+'.$delay.' seconds');
            $this->driver->release($queue, $envelope);
            $this->logger->info('Job released for retry', ['id' => $envelope->id, 'in' => $delay.'s']);
        }
    }

    private function applyRetryHints(Job $job, JobEnvelope $envelope): void
    {
        if ($job instanceof RetryableJob) {
            $envelope->maxAttempts = max(1, $job->getMaxAttempts());
        }
    }

    private function overMemory(int $limitMb): bool
    {
        $usage = memory_get_usage(true) / (1024 * 1024);
        return $usage > $limitMb;
    }
}

