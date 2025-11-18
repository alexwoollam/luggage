<?php

declare(strict_types=1);

namespace Luggage\Queue;

/**
 * File-based queue driver for tiny deployments.
 * Not intended for high concurrency; favors simplicity and atomic renames.
 */
final class FileQueueDriver implements QueueDriver
{
    public function __construct(private string $basePath)
    {
        $this->basePath = rtrim($this->basePath, DIRECTORY_SEPARATOR);
    }

    public function enqueue(string $queue, JobEnvelope $envelope): void
    {
        $dir = $this->queueDir($queue);
        $this->ensureDir($dir);
        $path = $dir . DIRECTORY_SEPARATOR . $this->fileName($envelope->id, 'ready');
        $this->writeJson($path, $envelope->toArray());
    }

    public function reserve(string $queue): ?JobEnvelope
    {
        $dir = $this->queueDir($queue);
        if (!is_dir($dir)) {
            return null;
        }
        $ready = glob($dir . DIRECTORY_SEPARATOR . '*.ready.json') ?: [];
        sort($ready, SORT_STRING);

        $nowTs = (new \DateTimeImmutable())->getTimestamp();
        foreach ($ready as $file) {
            $data = $this->readJson($file);
            if (!is_array($data)) {
                // Corrupt file; move aside
                $this->safeRename($file, $file . '.corrupt');
                continue;
            }

            $availableAt = (int) ($data['availableAt'] ?? 0);
            if ($availableAt > $nowTs) {
                continue;
            }

            $reserved = preg_replace('/\.ready\.json$/', '.reserved.json', $file);
            if ($reserved === null) {
                continue;
            }
            // Atomic claim
            if (@rename($file, $reserved)) {
                $data2 = $this->readJson($reserved);
                $env = JobEnvelope::fromArray($data2 ?: $data);
                return $env;
            }
        }
        return null;
    }

    public function ack(string $queue, JobEnvelope $envelope): void
    {
        $dir = $this->queueDir($queue);
        $reserved = $dir . DIRECTORY_SEPARATOR . $this->fileName($envelope->id, 'reserved');
        if (is_file($reserved)) {
            @unlink($reserved);
        }
    }

    public function release(string $queue, JobEnvelope $envelope): void
    {
        $dir = $this->queueDir($queue);
        $reserved = $dir . DIRECTORY_SEPARATOR . $this->fileName($envelope->id, 'reserved');
        $ready = $dir . DIRECTORY_SEPARATOR . $this->fileName($envelope->id, 'ready');
        // Update data
        if (is_file($reserved)) {
            $this->writeJson($reserved, $envelope->toArray());
            @rename($reserved, $ready);
            return;
        }
        // Fallback: write as ready
        $this->writeJson($ready, $envelope->toArray());
    }

    public function fail(string $queue, JobEnvelope $envelope, \Throwable $error): void
    {
        $dir = $this->deadDir($queue);
        $this->ensureDir($dir);
        $dead = $dir . DIRECTORY_SEPARATOR . $this->fileName($envelope->id, 'dead');
        $data = $envelope->toArray();
        $data['error'] = [
            'message' => $error->getMessage(),
            'code' => $error->getCode(),
            'type' => get_class($error),
        ];
        $this->writeJson($dead, $data);

        // Remove any reserved copy
        $reserved = $this->queueDir($queue) . DIRECTORY_SEPARATOR . $this->fileName($envelope->id, 'reserved');
        if (is_file($reserved)) {
            @unlink($reserved);
        }
    }

    private function queueDir(string $queue): string
    {
        return $this->basePath . DIRECTORY_SEPARATOR . $this->sanitize($queue);
    }

    private function deadDir(string $queue): string
    {
        return $this->queueDir($queue) . DIRECTORY_SEPARATOR . 'dead';
    }

    private function ensureDir(string $dir): void
    {
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
    }

    private function fileName(string $id, string $state): string
    {
        return sprintf('%s.%s.json', $id, $state);
    }

    private function sanitize(string $name): string
    {
        return preg_replace('/[^a-zA-Z0-9._-]/', '_', $name) ?? 'default';
    }

    /** @return array<string,mixed>|null */
    private function readJson(string $path): ?array
    {
        if (!is_file($path)) {
            return null;
        }
        $raw = @file_get_contents($path);
        if ($raw === false) {
            return null;
        }
        $data = json_decode($raw, true);
        return is_array($data) ? $data : null;
    }

    /** @param array<string,mixed> $data */
    private function writeJson(string $path, array $data): void
    {
        $tmp = $path . '.tmp';
        @file_put_contents($tmp, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        @rename($tmp, $path);
    }

    private function safeRename(string $from, string $to): void
    {
        @rename($from, $to);
    }
}

