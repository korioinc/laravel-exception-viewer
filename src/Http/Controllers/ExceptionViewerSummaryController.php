<?php

namespace Korioinc\ExceptionViewer\Http\Controllers;

use Illuminate\Database\DatabaseManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Korioinc\ExceptionViewer\Source\ExceptionSourceResolver;

class ExceptionViewerSummaryController
{
    private const TABLE = 'exception_logs';

    public function __construct(
        private readonly ExceptionSourceResolver $sourceResolver,
    ) {}

    public function __invoke(DatabaseManager $database): JsonResponse
    {
        $localSourceKey = $this->sourceResolver->localKey();
        $sourceExpression = "COALESCE(NULLIF(source_key, ''), ?)";

        $rows = $this->databaseConnection($database)
            ->table(self::TABLE)
            ->select('name')
            ->selectRaw($sourceExpression.' as service_name', [$localSourceKey])
            ->selectRaw('SUM(count) as exception_count')
            ->selectRaw('MAX(latest_at) as latest_at')
            ->groupBy('service_name')
            ->groupBy('name')
            ->get();

        $services = $rows
            ->map(fn (object $row): array => [
                'service_name' => (string) $row->service_name,
                'name' => (string) $row->name,
                'count' => (int) $row->exception_count,
                'latest_at' => Carbon::parse($row->latest_at)->format('Y-m-d H:i:s'),
            ])
            ->groupBy('service_name')
            ->map(function ($exceptions, string $serviceName) use ($localSourceKey): array {
                $exceptionSummaries = $exceptions
                    ->sortBy([
                        ['latest_at', 'desc'],
                        ['count', 'desc'],
                        ['name', 'asc'],
                    ])
                    ->values()
                    ->map(fn (array $exception): array => [
                        'name' => $exception['name'],
                        'count' => $exception['count'],
                        'latest_at' => $exception['latest_at'],
                    ])
                    ->all();

                return [
                    'name' => $serviceName,
                    'exceptions' => $exceptionSummaries,
                    'total_count' => count($exceptionSummaries),
                    'total_error_count' => array_sum(array_column($exceptionSummaries, 'count')),
                    'is_local' => $serviceName === $localSourceKey,
                ];
            })
            ->values()
            ->sortBy([
                ['is_local', 'desc'],
                ['name', 'asc'],
            ])
            ->values()
            ->map(fn (array $service): array => [
                'name' => $service['name'],
                'exceptions' => $service['exceptions'],
                'total_count' => $service['total_count'],
                'total_error_count' => $service['total_error_count'],
            ])
            ->all();

        return response()->json($services);
    }

    private function databaseConnection(DatabaseManager $database)
    {
        $connection = config('exception-viewer.database_connection');

        return $connection === null || $connection === ''
            ? $database->connection()
            : $database->connection($connection);
    }
}
