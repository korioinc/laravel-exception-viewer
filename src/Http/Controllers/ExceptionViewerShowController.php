<?php

namespace Korioinc\ExceptionViewer\Http\Controllers;

use Illuminate\Database\DatabaseManager;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Korioinc\ExceptionViewer\Source\ExceptionSourceResolver;
use Korioinc\ExceptionViewer\Viewer\ExceptionEntryFormatter;

class ExceptionViewerShowController
{
    private const TABLE = 'exception_logs';

    public function __construct(
        private readonly ExceptionEntryFormatter $formatter,
        private readonly ExceptionSourceResolver $sourceResolver,
    ) {}

    public function __invoke(string $key, Request $request, DatabaseManager $database): Response
    {
        $connection = $this->databaseConnection($database);
        $source = trim((string) $request->query('source', ''));
        $source = $source === '' ? $this->sourceResolver->localKey() : $source;
        $exception = $connection
            ->table(self::TABLE)
            ->where('key', $key)
            ->where('source_key', $source)
            ->orderByDesc('latest_at')
            ->first();

        abort_if($exception === null, 404);

        return response($this->formatter->toCopyMarkdown($exception), 200, [
            'Content-Type' => 'text/markdown; charset=UTF-8',
            'Content-Disposition' => 'inline; filename="exception-'.$key.'.md"',
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
