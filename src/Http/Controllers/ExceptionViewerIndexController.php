<?php

namespace Korioinc\ExceptionViewer\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Korioinc\ExceptionViewer\Source\ExceptionSourceResolver;
use Korioinc\ExceptionViewer\Viewer\ExceptionEntryFormatter;

class ExceptionViewerIndexController
{
    private const TABLE = 'exception_logs';

    public function __construct(
        private readonly ExceptionEntryFormatter $formatter,
        private readonly ExceptionSourceResolver $sourceResolver,
    ) {}

    public function __invoke(Request $request, DatabaseManager $database): View
    {
        $currentSort = $this->resolveSort((string) $request->query('sort', 'newest'));
        $connection = $this->databaseConnection($database);
        /** @var view-string $view */
        $view = 'exception-viewer::pages.index';
        $localSourceKey = $this->sourceResolver->localKey();

        $sources = $connection
            ->table(self::TABLE)
            ->select('source_key')
            ->selectRaw('count(*) as row_count')
            ->whereNotNull('source_key')
            ->groupBy('source_key')
            ->get()
            ->map(fn (object $source): array => [
                'key' => $source->source_key,
                'label' => $this->sourceLabel((string) $source->source_key, $localSourceKey),
                'row_count' => (int) $source->row_count,
                'is_local' => $source->source_key === $localSourceKey,
            ])
            ->sortBy([
                ['is_local', 'desc'],
                ['label', 'asc'],
                ['key', 'asc'],
            ])
            ->values();

        $selectedSource = $this->resolveSelectedSource(
            (string) $request->query('source', ''),
            $sources,
        );

        $exceptionsQuery = $connection
            ->table(self::TABLE)
            ->when($selectedSource !== null, fn ($query) => $query->where('source_key', $selectedSource));

        $exceptions = $this->applySort($exceptionsQuery, $currentSort)
            ->get()
            ->values()
            ->map(function (object $exception, int $index) use ($localSourceKey): array {
                $source = ($exception->source_key ?? null) === $localSourceKey
                    ? null
                    : ($exception->source_key ?? null);

                return $this->formatter->summarize($exception, $index) + [
                    'detail_url' => route('exception-viewer.show', array_filter([
                        'key' => $exception->key,
                        'source' => $source,
                    ], static fn (mixed $value): bool => $value !== null && $value !== '')),
                ];
            });

        $totalRowsQuery = $connection
            ->table(self::TABLE)
            ->when($selectedSource !== null, fn ($query) => $query->where('source_key', $selectedSource));

        return view($view, [
            'selectedSource' => $selectedSource,
            'localSourceKey' => $localSourceKey,
            'searchQuery' => '',
            'currentSort' => $currentSort,
            'sources' => $sources,
            'exceptions' => $exceptions,
            'totalRows' => $totalRowsQuery->count(),
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

    private function sourceLabel(string $sourceKey, string $localSourceKey): string
    {
        if ($sourceKey === $localSourceKey) {
            return 'Local App';
        }

        return strtoupper($sourceKey);
    }

    private function resolveSelectedSource(string $requestedSource, mixed $sources): ?string
    {
        if ($sources->isEmpty()) {
            return null;
        }

        if ($requestedSource !== '' && $sources->contains('key', $requestedSource)) {
            return $requestedSource;
        }

        $localKey = $this->sourceResolver->localKey();

        if ($sources->contains('key', $localKey)) {
            return $localKey;
        }

        return $sources->first()['key'];
    }
}
