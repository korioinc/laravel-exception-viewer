<?php

namespace Korioinc\ExceptionViewer\Tests\Fakes;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class NestedQueuedJob implements ShouldQueue
{
    use Dispatchable;
    use Queueable;

    public function __construct(
        public string $orderId,
        public string $password,
    ) {}

    public function handle(): void
    {
        try {
            dispatch_sync(new ThrowingQueuedJob('inner-456', 'inner-secret'));
        } catch (\Throwable $throwable) {
            report($throwable);
        }

        throw new \RuntimeException('Outer queued job exploded');
    }
}
