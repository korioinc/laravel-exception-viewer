<?php

namespace Korioinc\ExceptionViewer\Http\Controllers;

use Illuminate\Database\DatabaseManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Korioinc\ExceptionViewer\Source\ExceptionSourceResolver;

class ExceptionViewerPurgeController
{
    private const TABLE = 'exception_logs';

    public function __construct(
        private readonly ExceptionSourceResolver $sourceResolver,
    ) {}

    public function __invoke(Request $request, DatabaseManager $database): RedirectResponse
    {
        $connection = $this->databaseConnection($database);

        if ((string) $request->input('scope', 'source') === 'all') {
            $connection->table(self::TABLE)->delete();
        } else {
            $source = $this->resolveSource((string) $request->input('source', ''));

            $connection
                ->table(self::TABLE)
                ->where('source_key', $source)
                ->delete();
        }

        return redirect($this->resolveRedirectPath((string) $request->input('redirect_to', '')));
    }

    private function databaseConnection(DatabaseManager $database)
    {
        $connection = config('exception-viewer.database_connection');

        return $connection === null || $connection === ''
            ? $database->connection()
            : $database->connection($connection);
    }

    private function resolveSource(string $source): string
    {
        $source = trim($source);

        return $source === '' ? $this->sourceResolver->localKey() : $source;
    }

    private function resolveRedirectPath(string $path): string
    {
        if ($path !== '' && str_starts_with($path, '/') && ! str_starts_with($path, '//')) {
            return $path;
        }

        return route('exception-viewer.index');
    }
}
