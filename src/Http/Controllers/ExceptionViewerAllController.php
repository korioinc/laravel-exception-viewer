<?php

namespace Korioinc\ExceptionViewer\Http\Controllers;

use Illuminate\Database\DatabaseManager;
use Illuminate\Http\Response;
use Korioinc\ExceptionViewer\Viewer\ExceptionEntryFormatter;

class ExceptionViewerAllController
{
    private const TABLE = 'exception_logs';

    public function __construct(
        private readonly ExceptionEntryFormatter $formatter,
    ) {}

    public function __invoke(DatabaseManager $database): Response
    {
        $connection = $this->databaseConnection($database);
        $exceptions = $connection
            ->table(self::TABLE)
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
}
