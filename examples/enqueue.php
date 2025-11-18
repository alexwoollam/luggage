<?php

declare(strict_types=1);

require __DIR__ . '/../bin/luggage'; // loads fallback autoloader

use Luggage\Queue\FileQueueDriver;
use Luggage\Queue\JobEnvelope;

require __DIR__ . '/HelloJob.php';

$dir = __DIR__ . '/../storage/luggage';
@mkdir($dir, 0777, true);

$driver = new FileQueueDriver($dir);
$env = JobEnvelope::new(HelloJob::class, ['name' => 'Luggage']);
$driver->enqueue('default', $env);

echo "Enqueued job {$env->id}\n";

