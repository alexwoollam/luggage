<?php

declare(strict_types=1);

namespace Luggage\Worker;

use Luggage\Contracts\Job;

final class JobFactory
{
    /**
     * Instantiate a Job given its class and payload.
     * Prefers a static fromPayload(array): static factory if present,
     * otherwise attempts `new $class($payload)` or `new $class()` with setter.
     */
    public function make(string $jobClass, array $payload): Job
    {
        if (!class_exists($jobClass)) {
            throw new \InvalidArgumentException("Job class not found: {$jobClass}");
        }

        if (method_exists($jobClass, 'fromPayload')) {
            /** @var Job $job */
            $job = $jobClass::fromPayload($payload);
            return $job;
        }

        $ref = new \ReflectionClass($jobClass);
        if ($ref->isInstantiable()) {
            // Try ctor(array $payload)
            $ctor = $ref->getConstructor();
            if ($ctor && $ctor->getNumberOfParameters() === 1) {
                $params = $ctor->getParameters();
                $param = $params[0];
                if ($param->getType() && (string) $param->getType() === 'array') {
                    /** @var Job */
                    return $ref->newInstance($payload);
                }
            }
            // Try no-arg and setPayload(array $payload)
            /** @var Job $instance */
            $instance = $ref->newInstance();
            if (method_exists($instance, 'setPayload')) {
                $instance->setPayload($payload);
                return $instance;
            }
        }

        throw new \InvalidArgumentException("Cannot construct job {$jobClass} from payload.");
    }
}

