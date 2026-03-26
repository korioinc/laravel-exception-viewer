<?php

namespace Korioinc\ExceptionViewer\Http\Controllers;

use Illuminate\Database\DatabaseManager;
use Illuminate\Http\Response;
use Korioinc\ExceptionViewer\Viewer\ExceptionEntryFormatter;

class ExceptionViewerShowController
{
    private const TABLE = 'exception_logs';

    public function __construct(
        private readonly ExceptionEntryFormatter $formatter,
    ) {}

    public function __invoke(string $key, DatabaseManager $database): Response
    {
        $connection = $this->databaseConnection($database);
        $exception = $connection->table(self::TABLE)->where('key', $key)->first();

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
