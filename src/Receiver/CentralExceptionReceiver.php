<?php

namespace Korioinc\ExceptionViewer\Receiver;

use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Carbon;

class CentralExceptionReceiver
{
    private const TABLE = 'exception_logs';

    public function __construct(
        private readonly DatabaseManager $database,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function receive(array $payload): object
    {
        $connection = $this->databaseConnection();
        $source = $payload['source'];
        $exception = $payload['exception'];
        $request = $payload['request'] ?? [];
        $timestamp = now();
        $incomingLatestAt = Carbon::parse($exception['latest_at']);
        $sourceKey = (string) $source['key'];
        $key = (string) $exception['key'];
        $attributes = [
            'source_key' => $sourceKey,
            'received_at' => $timestamp,
            'key' => $key,
            'name' => $exception['name'],
            'message' => $exception['message'],
            'file' => $exception['file'],
            'line' => (int) $exception['line'],
            'raw_exception' => $exception['raw_exception'],
            'request_method' => $request['method'] ?? null,
            'request_endpoint' => $request['endpoint'] ?? null,
            'request_headers' => $request['headers'] ?? null,
            'request_payload' => $request['payload'] ?? null,
            'count' => max(1, (int) $exception['count']),
            'latest_at' => $incomingLatestAt,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ];

        $inserted = $connection->table(self::TABLE)->insertOrIgnore($attributes);

        if ($inserted !== 0) {
            return $this->find($sourceKey, $key);
        }

        $existing = $this->find($sourceKey, $key);
        $storedCount = max(1, (int) $existing->count);
        $incomingExceptionCount = max(1, (int) $exception['count']);
        $incomingCount = max($storedCount, $incomingExceptionCount);
        $storedLatestAt = Carbon::parse($existing->latest_at);
        $shouldRefreshDetails = $incomingLatestAt->greaterThan($storedLatestAt)
            || ($incomingLatestAt->equalTo($storedLatestAt) && $incomingExceptionCount > $storedCount);
        $updates = [
            'count' => $incomingCount,
            'received_at' => $timestamp,
            'updated_at' => $timestamp,
        ];

        if ($shouldRefreshDetails) {
            $updates += [
                'name' => $exception['name'],
                'message' => $exception['message'],
                'file' => $exception['file'],
                'line' => (int) $exception['line'],
                'raw_exception' => $exception['raw_exception'],
                'request_method' => $request['method'] ?? null,
                'request_endpoint' => $request['endpoint'] ?? null,
                'request_headers' => $request['headers'] ?? null,
                'request_payload' => $request['payload'] ?? null,
                'latest_at' => $incomingLatestAt,
            ];
        }

        $connection->table(self::TABLE)
            ->where('source_key', $sourceKey)
            ->where('key', $key)
            ->update($updates);

        return $this->find($sourceKey, $key);
    }

    private function find(string $sourceKey, string $key): object
    {
        return $this->databaseConnection()
            ->table(self::TABLE)
            ->where('source_key', $sourceKey)
            ->where('key', $key)
            ->first();
    }

    private function databaseConnection()
    {
        $connection = config('exception-viewer.database_connection');

        return $connection === null || $connection === ''
            ? $this->database->connection()
            : $this->database->connection($connection);
    }
}
