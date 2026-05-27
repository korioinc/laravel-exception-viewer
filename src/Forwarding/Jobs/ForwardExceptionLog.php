<?php

namespace Korioinc\ExceptionViewer\Forwarding\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Korioinc\ExceptionViewer\Forwarding\ExceptionForwardingClient;

class ForwardExceptionLog implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public readonly array $payload,
    ) {}

    public function handle(ExceptionForwardingClient $client): void
    {
        $client->send($this->payload);
    }

    public function tries(): int
    {
        return max(1, (int) config('exception-viewer.forwarding.tries', 3));
    }

    public function backoff(): array|int
    {
        $backoff = config('exception-viewer.forwarding.backoff', 60);

        if (is_array($backoff)) {
            return array_map(static fn (mixed $value): int => max(0, (int) $value), $backoff);
        }

        return max(0, (int) $backoff);
    }
}
