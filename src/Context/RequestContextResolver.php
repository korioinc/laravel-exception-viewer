<?php

namespace Korioinc\ExceptionViewer\Context;

use BackedEnum;
use DateTimeInterface;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use JsonSerializable;
use Stringable;
use UnitEnum;

class RequestContextResolver
{
    private const JSON_FLAGS = JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE;

    public function __construct(
        private readonly Application $app,
        private readonly QueueContextStore $queueContextStore,
    ) {}

    public function resolve(): array
    {
        if (! config('exception-viewer.request_context.enabled', true)) {
            return $this->emptyContext();
        }

        if ($this->queueContextStore->current() !== null) {
            return $this->resolveQueueContext();
        }

        $request = $this->currentRequest();

        if (! $request instanceof Request) {
            return $this->emptyContext();
        }

        return $this->buildContext(
            $request->getMethod(),
            $request->getRequestUri(),
            $request->headers->all(),
            $request->all(),
        );
    }

    private function currentRequest(): ?Request
    {
        if (! $this->app->bound('request')) {
            return null;
        }

        /** @var mixed $request */
        $request = $this->app->make('request');

        if (! $request instanceof Request) {
            return null;
        }

        if (! $request->server->has('REQUEST_METHOD')) {
            return null;
        }

        if ($this->app->runningInConsole() && $request->route() === null && $request->getPathInfo() === '/') {
            return null;
        }

        return $request;
    }

    private function emptyContext(): array
    {
        return [
            'request_method' => null,
            'request_endpoint' => null,
            'request_headers' => null,
            'request_payload' => null,
        ];
    }

    private function resolveQueueContext(): array
    {
        $context = $this->queueContextStore->current();

        if ($context === null) {
            return $this->emptyContext();
        }

        return $this->buildContext(
            $context['request_method'] ?? null,
            $context['request_endpoint'] ?? null,
            $context['request_headers'] ?? null,
            $context['request_payload'] ?? null,
        );
    }

    private function buildContext(?string $method, ?string $endpoint, mixed $headers, mixed $payload): array
    {
        $maskedHeaders = $this->maskValue($headers);
        $maskedPayload = $this->maskValue($payload);

        return [
            'request_method' => $method,
            'request_endpoint' => $endpoint,
            'request_headers' => $this->encodeStructuredData($maskedHeaders, $this->resolveLimit('exception-viewer.request_context.max_headers_size')),
            'request_payload' => $this->encodeStructuredData($maskedPayload, $this->resolveLimit('exception-viewer.request_context.max_payload_size')),
        ];
    }

    private function encodeStructuredData(mixed $value, ?int $maxBytes): ?string
    {
        if ($value === null || $value === []) {
            return null;
        }

        $normalized = $this->normalizeValue($value);
        $json = json_encode($normalized, self::JSON_FLAGS);

        if ($maxBytes === null || strlen($json) <= $maxBytes) {
            return $json;
        }

        return json_encode([
            'truncated' => true,
            'original_size' => strlen($json),
            'preview' => Str::limit($json, min($maxBytes, 512), ''),
        ], self::JSON_FLAGS);
    }

    private function resolveLimit(string $key): ?int
    {
        $limit = config($key);

        if (! is_numeric($limit)) {
            return null;
        }

        $limit = (int) $limit;

        return $limit > 0 ? $limit : null;
    }

    private function maskValue(mixed $value, ?string $key = null): mixed
    {
        if ($key !== null && $this->shouldMask($key)) {
            return '[MASKED]';
        }

        if (is_array($value)) {
            $masked = [];

            foreach ($value as $nestedKey => $nestedValue) {
                $masked[$nestedKey] = $this->maskValue($nestedValue, is_string($nestedKey) ? $nestedKey : null);
            }

            return $masked;
        }

        return $value;
    }

    private function shouldMask(string $key): bool
    {
        $normalizedKey = Str::lower($key);
        $maskedKeys = array_map(static fn (mixed $maskedKey): string => Str::lower((string) $maskedKey), config('exception-viewer.request_context.masked_keys', []));

        return in_array($normalizedKey, $maskedKeys, true);
    }

    private function normalizeValue(mixed $value): mixed
    {
        if (is_array($value)) {
            $normalized = [];

            foreach ($value as $key => $item) {
                $normalized[$key] = $this->normalizeValue($item);
            }

            return $normalized;
        }

        if (is_scalar($value) || $value === null) {
            return $value;
        }

        if ($value instanceof BackedEnum) {
            return $value->value;
        }

        if ($value instanceof UnitEnum) {
            return $value->name;
        }

        if ($value instanceof UploadedFile) {
            return [
                'uploaded_file' => [
                    'original_name' => $value->getClientOriginalName(),
                    'mime_type' => $value->getClientMimeType(),
                    'size' => $value->getSize(),
                ],
            ];
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format(DateTimeInterface::ATOM);
        }

        if ($value instanceof JsonSerializable) {
            return $this->normalizeValue($value->jsonSerialize());
        }

        if ($value instanceof Stringable) {
            return (string) $value;
        }

        if (is_object($value) && method_exists($value, '__toString')) {
            return (string) $value;
        }

        if (is_object($value)) {
            return [
                'object' => $value::class,
            ];
        }

        return '['.get_debug_type($value).']';
    }
}
