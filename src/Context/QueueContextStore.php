<?php

namespace Korioinc\ExceptionViewer\Context;

use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Queue\Job;
use ReflectionClass;
use ReflectionProperty;
use Throwable;

class QueueContextStore
{
    private array $frames = [];

    public function __construct(
        private readonly Application $app,
    ) {}

    public function capture(Job $job): void
    {
        $this->frames[] = [
            'job' => $job,
            'resolved_context' => null,
            'pending_exception_ids' => [],
        ];
    }

    public function current(): ?array
    {
        $frame = $this->currentFrame();

        if ($frame === null) {
            return null;
        }

        if (is_array($frame['resolved_context'])) {
            return $frame['resolved_context'];
        }

        $resolvedContext = [
            'request_method' => 'JOB',
            'request_endpoint' => $frame['job']->resolveQueuedJobClass(),
            'request_headers' => $this->resolveHeaders($frame['job']),
            'request_payload' => $this->resolvePayload($frame['job']),
        ];

        $this->frames[array_key_last($this->frames)]['resolved_context'] = $resolvedContext;

        return $resolvedContext;
    }

    public function currentJobClass(): ?string
    {
        $frame = $this->currentFrame();

        if ($frame === null) {
            return null;
        }

        return $frame['job']->resolveQueuedJobClass();
    }

    public function markException(Job $job, Throwable $throwable): void
    {
        $index = $this->findFrameIndex($job);

        if ($index === null) {
            return;
        }

        $exceptionId = spl_object_id($throwable);

        if (! in_array($exceptionId, $this->frames[$index]['pending_exception_ids'], true)) {
            $this->frames[$index]['pending_exception_ids'][] = $exceptionId;
        }
    }

    public function complete(Job $job): void
    {
        $index = $this->findFrameIndex($job);

        if ($index === null) {
            return;
        }

        array_splice($this->frames, $index, 1);
    }

    public function completeReportedException(Throwable $throwable): void
    {
        $exceptionId = spl_object_id($throwable);

        while ($frame = $this->currentFrame()) {
            if (! in_array($exceptionId, $frame['pending_exception_ids'], true)) {
                return;
            }

            array_pop($this->frames);
        }
    }

    public function clear(): void
    {
        $this->frames = [];
    }

    private function resolvePayload(Job $job): mixed
    {
        $payload = $job->payload();
        $command = $payload['data']['command'] ?? null;

        if (! is_string($command) || $command === '') {
            return $this->sanitizePayload($payload['data'] ?? $payload);
        }

        try {
            $resolved = $this->resolveCommand($command);

            if (is_object($resolved)) {
                return $this->extractObjectProperties($resolved);
            }
        } catch (Throwable) {
            return $this->sanitizePayload($payload['data'] ?? $payload);
        }

        return $this->sanitizePayload($payload['data'] ?? $payload);
    }

    private function resolveCommand(string $command): mixed
    {
        if (str_starts_with($command, 'O:')) {
            return unserialize($command);
        }

        if (! $this->app->bound(Encrypter::class)) {
            return unserialize($command);
        }

        return unserialize($this->app->make(Encrypter::class)->decrypt($command));
    }

    private function resolveHeaders(Job $job): array
    {
        return [
            'queue' => $job->getQueue(),
            'attempts' => $job->attempts(),
            'job_id' => $job->getJobId(),
            'job_name' => $job->resolveName(),
        ];
    }

    private function currentFrame(): ?array
    {
        if ($this->frames === []) {
            return null;
        }

        $frame = $this->frames[array_key_last($this->frames)];

        return is_array($frame) ? $frame : null;
    }

    private function findFrameIndex(Job $job): ?int
    {
        foreach (array_reverse($this->frames, true) as $index => $frame) {
            if (($frame['job'] ?? null) === $job) {
                return $index;
            }
        }

        return null;
    }

    private function extractObjectProperties(object $object): array
    {
        $properties = [];
        $reflection = new ReflectionClass($object);

        do {
            foreach ($reflection->getProperties() as $property) {
                if ($property->isStatic()) {
                    continue;
                }

                $name = $property->getName();

                if (array_key_exists($name, $properties)) {
                    continue;
                }

                $properties[$name] = $this->propertyValue($property, $object);
            }
        } while ($reflection = $reflection->getParentClass());

        return $this->sanitizePayload($properties);
    }

    private function sanitizePayload(mixed $payload): mixed
    {
        if (! is_array($payload)) {
            return $payload;
        }

        unset($payload['connection'], $payload['queue']);

        return array_filter(
            $payload,
            static fn (mixed $value): bool => $value !== null && $value !== [] && $value !== '',
        );
    }

    private function propertyValue(ReflectionProperty $property, object $object): mixed
    {
        if (! $property->isInitialized($object)) {
            return null;
        }

        return $property->getValue($object);
    }
}
