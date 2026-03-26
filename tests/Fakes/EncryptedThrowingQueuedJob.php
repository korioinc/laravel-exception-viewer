<?php

namespace Korioinc\ExceptionViewer\Tests\Fakes;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class EncryptedThrowingQueuedJob implements ShouldBeEncrypted, ShouldQueue
{
    use Dispatchable;
    use Queueable;

    public function __construct(
        public string $orderId,
        public string $password,
    ) {}

    public function handle(): void
    {
        throw new \RuntimeException('Encrypted queued job exploded');
    }
}
