<?php

namespace Korioinc\ExceptionViewer\Tests\Fakes;

use Illuminate\Contracts\Queue\Job;

class NamedQueueJob implements Job
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        private readonly string $jobClass,
        private readonly array $payload = [],
    ) {}

    public function uuid()
    {
        return null;
    }

    public function getJobId()
    {
        return 'queue-job-id';
    }

    public function payload()
    {
        return [
            'data' => $this->payload,
        ];
    }

    public function fire(): void {}

    public function release($delay = 0): void {}

    public function isReleased(): bool
    {
        return false;
    }

    public function delete(): void {}

    public function isDeleted(): bool
    {
        return false;
    }

    public function isDeletedOrReleased(): bool
    {
        return false;
    }

    public function attempts(): int
    {
        return 1;
    }

    public function hasFailed(): bool
    {
        return false;
    }

    public function markAsFailed(): void {}

    public function fail($e = null): void {}

    public function maxTries()
    {
        return null;
    }

    public function maxExceptions()
    {
        return null;
    }

    public function timeout()
    {
        return null;
    }

    public function retryUntil()
    {
        return null;
    }

    public function getName(): string
    {
        return $this->jobClass;
    }

    public function resolveName(): string
    {
        return $this->jobClass;
    }

    public function resolveQueuedJobClass(): string
    {
        return $this->jobClass;
    }

    public function getConnectionName()
    {
        return 'sync';
    }

    public function getQueue()
    {
        return 'default';
    }

    public function getRawBody()
    {
        return '{}';
    }
}
