<?php

namespace Korioinc\ExceptionViewer\Forwarding;

use Illuminate\Contracts\Bus\Dispatcher;
use Korioinc\ExceptionViewer\Context\QueueContextStore;
use Korioinc\ExceptionViewer\Forwarding\Jobs\ForwardExceptionLog;
use Korioinc\ExceptionViewer\Source\ExceptionSourceResolver;

class ExceptionForwarder
{
    public function __construct(
        private readonly Dispatcher $dispatcher,
        private readonly ExceptionLogSnapshotBuilder $snapshotBuilder,
        private readonly ExceptionSourceResolver $sourceResolver,
        private readonly QueueContextStore $queueContextStore,
        private readonly ExceptionForwardingClient $client,
    ) {}

    public function queue(object $exception): void
    {
        if (! config('exception-viewer.enabled', true)) {
            return;
        }

        if (! config('exception-viewer.forwarding.enabled', false)) {
            return;
        }

        if ($this->queueContextStore->currentJobClass() === ForwardExceptionLog::class) {
            return;
        }

        if ($this->sourceResolver->configuredKey() === '') {
            return;
        }

        if ($this->endpoint() === '' || $this->apiKey() === '') {
            return;
        }

        $payload = $this->snapshotBuilder->build($exception);

        if ($this->mode() === 'sync') {
            $this->client->send($payload);

            return;
        }

        $job = new ForwardExceptionLog($payload);
        $queue = config('exception-viewer.forwarding.queue');

        if (is_string($queue) && trim($queue) !== '') {
            $job->onQueue($queue);
        }

        $this->dispatcher->dispatch($job);
    }

    private function endpoint(): string
    {
        return trim((string) config('exception-viewer.forwarding.endpoint', ''));
    }

    private function apiKey(): string
    {
        return trim((string) config('exception-viewer.forwarding.api_key', ''));
    }

    private function mode(): string
    {
        return strtolower(trim((string) config('exception-viewer.forwarding.mode', 'queue')));
    }
}
