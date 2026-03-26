<?php

namespace Korioinc\ExceptionViewer\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Korioinc\ExceptionViewer\Viewer\ExceptionEntryFormatter;

class ExceptionViewerIndexController
{
    private const TABLE = 'exception_logs';

    public function __construct(
        private readonly ExceptionEntryFormatter $formatter,
    ) {}

    public function __invoke(Request $request, DatabaseManager $database): View
    {
        $selectedGroup = (string) $request->query('group', 'all');
        $currentSort = $this->resolveSort((string) $request->query('sort', 'newest'));
        $connection = $this->databaseConnection($database);

        $groups = $connection
            ->table(self::TABLE)
            ->select('name')
            ->selectRaw('count(*) as row_count')
            ->groupBy('name')
            ->orderBy('name')
            ->get()
            ->map(fn (object $group): array => [
                'name' => $group->name,
                'row_count' => (int) $group->row_count,
            ]);

        $exceptionsQuery = $connection
            ->table(self::TABLE)
            ->when($selectedGroup !== 'all', fn ($query) => $query->where('name', $selectedGroup));

        $exceptions = $this->applySort($exceptionsQuery, $currentSort)
            ->get()
            ->values()
            ->map(function (object $exception, int $index): array {
                return $this->formatter->summarize($exception, $index) + [
                    'detail_url' => route('exception-viewer.show', ['key' => $exception->key]),
                ];
            });

        return view('exception-viewer::pages.index', [
            'selectedGroup' => $selectedGroup,
            'searchQuery' => '',
            'currentSort' => $currentSort,
            'groups' => $groups,
            'exceptions' => $exceptions,
            'totalRows' => $connection->table(self::TABLE)->count(),
            'assetsPathUrl' => asset(trim((string) config('exception-viewer.assets_path', 'vendor/exception-viewer'), '/')),
        ]);
    }

    private function databaseConnection(DatabaseManager $database)
    {
        $connection = config('exception-viewer.database_connection');

        return $connection === null || $connection === ''
            ? $database->connection()
            : $database->connection($connection);
    }

    private function resolveSort(string $sort): string
    {
        return match ($sort) {
            'oldest', 'count' => $sort,
            default => 'newest',
        };
    }

    private function applySort(Builder $query, string $sort): Builder
    {
        return match ($sort) {
            'oldest' => $query
                ->orderBy('latest_at')
                ->orderByDesc('count'),
            'count' => $query
                ->orderByDesc('count')
                ->orderByDesc('latest_at'),
            default => $query
                ->orderByDesc('latest_at')
                ->orderByDesc('count'),
        };
    }
}
