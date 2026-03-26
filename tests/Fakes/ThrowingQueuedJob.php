<?php

namespace Korioinc\ExceptionViewer\Tests\Fakes;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class ThrowingQueuedJob implements ShouldQueue
{
    use Dispatchable;
    use Queueable;

    public function __construct(
        public string $orderId,
        public string $password,
    ) {}

    public function handle(): void
    {
        throw new \RuntimeException('Queued job exploded');
    }
}
