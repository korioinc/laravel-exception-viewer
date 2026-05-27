<?php

namespace Korioinc\ExceptionViewer\Forwarding;

use Korioinc\ExceptionViewer\Source\ExceptionSourceResolver;

class ExceptionLogSnapshotBuilder
{
    public function __construct(
        private readonly ExceptionSourceResolver $sourceResolver,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(object $exception): array
    {
        return [
            'version' => 1,
            'source' => [
                'key' => $this->sourceResolver->forwardingKey(),
            ],
            'exception' => [
                'key' => $exception->key,
                'name' => $exception->name,
                'message' => $exception->message,
                'file' => $exception->file,
                'line' => (int) $exception->line,
                'raw_exception' => $exception->raw_exception,
                'count' => (int) $exception->count,
                'latest_at' => (string) $exception->latest_at,
            ],
            'request' => [
                'method' => $exception->request_method,
                'endpoint' => $exception->request_endpoint,
                'headers' => $exception->request_headers,
                'payload' => $exception->request_payload,
            ],
            'sent_at' => now()->toIso8601String(),
        ];
    }
}
