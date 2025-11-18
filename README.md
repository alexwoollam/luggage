<p align="center">
  <img src="docs/luggage.svg" alt="Luggage logo" width="240" />
</p>

Luggage — a lightweight queue runner for small PHP apps

Why carry a whole postal service when a small suitcase will do? Luggage gives you a tiny, SOLID background task runner with a clean worker API, retries, and simple queue drivers — just enough to get jobs done.

Features
- Clean Job interface with a tiny Worker API
- Retry logic with exponential backoff
- In-memory and file-based queue drivers
- No external services required
- Small footprint; stays out of your way

Install
- Add to your project with Composer (package name: `luggage/luggage`).
- Autoloads via PSR-4 namespace `Luggage\\`.

Quick Start
1) Define a job
```php
use Luggage\Contracts\Job;

final class HelloJob implements Job
{
    public function __construct(private array $payload) {}
    public function handle(): void
    {
        echo "Hello, {$this->payload['name']}!\n";
    }
}
```

2) Enqueue the job
```php
use Luggage\Queue\JobEnvelope;
use Luggage\Queue\FileQueueDriver;

$driver = new FileQueueDriver(__DIR__.'/storage/luggage');
$envelope = JobEnvelope::new(HelloJob::class, ['name' => 'World']);
$driver->enqueue('default', $envelope);
```

3) Run the worker
```bash
php bin/luggage --dir=storage/luggage --queue=default
```

Retry Behavior
- Jobs fail by throwing; the worker increments attempts and schedules a retry.
- Default: max 3 attempts, exponential backoff (1s, 2s, 4s...).
- Implement `RetryableJob` to customize attempts and backoff.

Drivers
- InMemoryQueueDriver: great for tests and single-process embedding.
- FileQueueDriver: stores messages as JSON files in per-queue directories, using atomic renames for simple reservation semantics. A `dead` subdirectory collects permanent failures.

CLI
- `bin/luggage` options:
  - `--dir` base storage path (default `./storage/luggage`)
  - `--queue` queue name (default `default`)
  - `--sleep` idle sleep seconds (default `1`)
  - `--once` process a single job and exit
  - `--stop-on-empty` exit when no jobs are available
  - `--memory` soft memory limit in MB (0 disables)
  - `--debug` verbose logging

Testing
- Requires PHPUnit (^10 or ^11) as a dev dependency.
- Run tests:
  - `composer install`
  - `composer test`

Design Notes
- SOLID-focused: small interfaces, clear responsibilities, dependency inversion (worker depends on `QueueDriver` and `JobFactory`).
- Job construction is delegated to a factory to avoid mixing serialization concerns into job logic.
- File driver is intentionally simple; it’s not a high-concurrency system. Built for small deployments, not high-throughput pipelines.

License
MIT
