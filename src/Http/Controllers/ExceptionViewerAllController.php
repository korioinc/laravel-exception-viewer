<?php

namespace Korioinc\ExceptionViewer\Http\Controllers;

use Illuminate\Database\DatabaseManager;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Korioinc\ExceptionViewer\Source\ExceptionSourceResolver;
use Korioinc\ExceptionViewer\Viewer\ExceptionEntryFormatter;

class ExceptionViewerAllController
{
    private const TABLE = 'exception_logs';

    public function __construct(
        private readonly ExceptionEntryFormatter $formatter,
        private readonly ExceptionSourceResolver $sourceResolver,
    ) {}

    public function __invoke(Request $request, DatabaseManager $database): Response
    {
        $connection = $this->databaseConnection($database);
        $source = $this->resolveSourceFilter($request, $connection);
        $exceptions = $connection
            ->table(self::TABLE)
            ->when($source !== null, fn ($query) => $query->where('source_key', $source))
            ->orderByDesc('latest_at')
            ->orderByDesc('count')
            ->get();

        return response($this->formatter->toCopyMarkdownCollection($exceptions), 200, [
            'Content-Type' => 'text/markdown; charset=UTF-8',
            'Content-Disposition' => 'inline; filename="exceptions-all.md"',
        ]);
    }

    private function databaseConnection(DatabaseManager $database)
    {
        $connection = config('exception-viewer.database_connection');

        return $connection === null || $connection === ''
            ? $database->connection()
            : $database->connection($connection);
    }

    private function resolveSourceFilter(Request $request, mixed $connection): ?string
    {
        $requestedSource = trim((string) $request->query('source', ''));

        if ($requestedSource !== '') {
            return $requestedSource;
        }

        $localSourceKey = $this->sourceResolver->localKey();
        $hasLocalSource = $connection
            ->table(self::TABLE)
            ->where('source_key', $localSourceKey)
            ->exists();

        return $hasLocalSource ? $localSourceKey : null;
    }
}
