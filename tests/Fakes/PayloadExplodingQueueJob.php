<?php

namespace Korioinc\ExceptionViewer\Tests\Fakes;

use Illuminate\Contracts\Queue\Job;

class PayloadExplodingQueueJob implements Job
{
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
        throw new \RuntimeException('payload should not be resolved during capture');
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
        return self::class;
    }

    public function resolveName(): string
    {
        return self::class;
    }

    public function resolveQueuedJobClass(): string
    {
        return self::class;
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
