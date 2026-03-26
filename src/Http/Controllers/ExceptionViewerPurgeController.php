<?php

namespace Korioinc\ExceptionViewer\Http\Controllers;

use Illuminate\Database\DatabaseManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ExceptionViewerPurgeController
{
    private const TABLE = 'exception_logs';

    public function __invoke(Request $request, DatabaseManager $database): RedirectResponse
    {
        $connection = $this->databaseConnection($database);
        $connection->table(self::TABLE)->delete();

        return redirect($this->resolveRedirectPath((string) $request->input('redirect_to', '')));
    }

    private function databaseConnection(DatabaseManager $database)
    {
        $connection = config('exception-viewer.database_connection');

        return $connection === null || $connection === ''
            ? $database->connection()
            : $database->connection($connection);
    }

    private function resolveRedirectPath(string $path): string
    {
        if ($path !== '' && str_starts_with($path, '/') && ! str_starts_with($path, '//')) {
            return $path;
        }

        return route('exception-viewer.index');
    }
}
