<?php

namespace Korioinc\ExceptionViewer\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\DatabaseManager;

class PruneExceptionLogsCommand extends Command
{
    private const TABLE = 'exception_logs';

    private const RETENTION_DAYS = 14;

    protected $signature = 'exception-viewer:prune';

    protected $description = 'Delete exception logs that are at least fourteen days old.';

    public function handle(DatabaseManager $database): int
    {
        $deleted = $this->databaseConnection($database)
            ->table(self::TABLE)
            ->where('latest_at', '<=', now()->subDays(self::RETENTION_DAYS))
            ->delete();

        $this->info("Deleted {$deleted} exception log(s).");

        return self::SUCCESS;
    }

    private function databaseConnection(DatabaseManager $database)
    {
        $connection = config('exception-viewer.database_connection');

        return $connection === null || $connection === ''
            ? $database->connection()
            : $database->connection($connection);
    }
}
