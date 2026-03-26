<?php

namespace Korioinc\ExceptionViewer\Recording;

use Illuminate\Database\DatabaseManager;
use Korioinc\ExceptionViewer\Context\RequestContextResolver;
use Monolog\Formatter\LineFormatter;
use Monolog\Level;
use Monolog\LogRecord;
use Throwable;

class ExceptionLogRecorder
{
    private const TABLE = 'exception_logs';

    public function __construct(
        private readonly DatabaseManager $database,
        private readonly ExceptionFingerprint $fingerprint,
        private readonly RequestContextResolver $requestContextResolver,
    ) {}

    public function record(Throwable $throwable): ?string
    {
        if (! config('exception-viewer.enabled', true)) {
            return null;
        }

        $fingerprintKey = $this->fingerprint->make($throwable);

        try {
            $this->persist($throwable, $fingerprintKey);
        } catch (Throwable) {
            // Recording must never interrupt Laravel's native exception flow.
        }

        return $fingerprintKey;
    }

    private function persist(Throwable $throwable, string $fingerprintKey): void
    {
        $connection = $this->databaseConnection();
        $table = $connection->table(self::TABLE);
        $timestamp = now();
        $requestContext = $this->requestContextResolver->resolve();

        $attributes = [
            'key' => $fingerprintKey,
            'name' => $throwable::class,
            'message' => $throwable->getMessage(),
            'file' => $throwable->getFile(),
            'line' => $throwable->getLine(),
            'raw_exception' => $this->formatRawException($throwable),
            'request_method' => $requestContext['request_method'],
            'request_endpoint' => $requestContext['request_endpoint'],
            'request_headers' => $requestContext['request_headers'],
            'request_payload' => $requestContext['request_payload'],
            'count' => 1,
            'latest_at' => $timestamp,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ];

        $inserted = $table->insertOrIgnore($attributes);

        if ($inserted !== 0) {
            return;
        }

        $table
            ->where('key', $attributes['key'])
            ->update([
                'name' => $attributes['name'],
                'message' => $attributes['message'],
                'file' => $attributes['file'],
                'line' => $attributes['line'],
                'raw_exception' => $attributes['raw_exception'],
                'request_method' => $attributes['request_method'],
                'request_endpoint' => $attributes['request_endpoint'],
                'request_headers' => $attributes['request_headers'],
                'request_payload' => $attributes['request_payload'],
                'count' => $connection->raw('count + 1'),
                'latest_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);
    }

    private function databaseConnection()
    {
        $connection = config('exception-viewer.database_connection');

        return $connection === null || $connection === ''
            ? $this->database->connection()
            : $this->database->connection($connection);
    }

    private function formatRawException(Throwable $throwable): string
    {
        $formatter = new LineFormatter(null, 'Y-m-d H:i:s', true, true, true);

        return rtrim($formatter->format(new LogRecord(
            datetime: now()->toDateTimeImmutable(),
            channel: (string) config('logging.default', 'stack'),
            level: Level::Error,
            message: $throwable->getMessage(),
            context: [
                'exception' => $throwable,
            ],
            extra: [],
        )));
    }
}
