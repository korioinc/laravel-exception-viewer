<?php

namespace Korioinc\ExceptionViewer\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Korioinc\ExceptionViewer\Source\ExceptionSourceResolver;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Output\BufferedOutput;
use Throwable;

class ExceptionDigestDiscordCommand extends Command
{
    private const TABLE = 'exception_logs';

    private const SECTION_SEPARATOR = '----------------------';

    private const DISCORD_EMBED_DESCRIPTION_LIMIT = 4096;

    private const DISCORD_CODE_BLOCK_OVERHEAD = 12;

    private const DISCORD_DIGEST_CHUNK_LIMIT = self::DISCORD_EMBED_DESCRIPTION_LIMIT - self::DISCORD_CODE_BLOCK_OVERHEAD;

    private const DISCORD_EMBED_COLOR = 16753920;

    protected $signature = 'exception-viewer:discord-digest';

    protected $description = 'Send a table-formatted exception digest to Discord.';

    public function __construct(
        private readonly ExceptionSourceResolver $sourceResolver,
    ) {
        parent::__construct();
    }

    public function handle(DatabaseManager $database): int
    {
        $webhookUrl = trim((string) config('exception-viewer.digest_discord_webhook_url', ''));

        if ($webhookUrl === '') {
            $this->error('Discord digest webhook is not configured.');

            return self::FAILURE;
        }

        $generatedAt = Carbon::now();
        $dayStart = $generatedAt->copy()->startOfDay();

        $newErrors = $this->newErrors($database, $dayStart);
        $previousErrors = $this->previousErrors($database, $dayStart);
        $digest = $this->buildDigest($newErrors, $previousErrors, $generatedAt);
        $discordDigest = $this->buildDiscordDigest($newErrors, $previousErrors, $generatedAt);

        $this->writeDigest($digest);

        return $this->sendDigestToDiscord($webhookUrl, $discordDigest);
    }

    /**
     * @return array<int, object>
     */
    private function newErrors(DatabaseManager $database, Carbon $dayStart): array
    {
        return $this->baseExceptionQuery($database)
            ->where('created_at', '>=', $dayStart)
            ->get()
            ->all();
    }

    /**
     * @return array<int, object>
     */
    private function previousErrors(DatabaseManager $database, Carbon $dayStart): array
    {
        return $this->baseExceptionQuery($database)
            ->where('created_at', '<', $dayStart)
            ->get()
            ->all();
    }

    private function baseExceptionQuery(DatabaseManager $database)
    {
        return $this->databaseConnection($database)
            ->table(self::TABLE)
            ->select([
                'source_key',
                'key',
                'name',
                'file',
                'line',
                'count',
                'latest_at',
                'created_at',
            ])
            ->orderByDesc('latest_at')
            ->orderByDesc('count')
            ->orderBy('name');
    }

    /**
     * @param  array<int, object>  $newErrors
     * @param  array<int, object>  $previousErrors
     */
    private function buildDigest(
        array $newErrors,
        array $previousErrors,
        Carbon $generatedAt,
        bool $omitPreviousDetails = false,
    ): string {
        $groups = $this->groupErrorsBySource($previousErrors, $newErrors);

        return implode(PHP_EOL, [
            '[Daily] Exception Digest',
            '',
            'Summary ('.$generatedAt->format('Y-m-d H:i:s').')',
            $this->renderSummaryTable($groups),
            '',
            $this->renderSourceGroups($groups, $omitPreviousDetails),
        ]);
    }

    /**
     * @param  array<int, object>  $newErrors
     * @param  array<int, object>  $previousErrors
     */
    private function buildDiscordDigest(array $newErrors, array $previousErrors, Carbon $generatedAt): string
    {
        $digest = $this->buildDigest($newErrors, $previousErrors, $generatedAt);

        if ($this->fitsDiscordEmbedDescription($digest)) {
            return $digest;
        }

        return $this->buildDigest($newErrors, $previousErrors, $generatedAt, true);
    }

    /**
     * @param  array<string, array{previous: array<int, object>, new: array<int, object>}>  $groups
     */
    private function renderSummaryTable(array $groups): string
    {
        $rows = [];

        foreach ($groups as $sourceKey => $group) {
            $rows[] = [
                $sourceKey,
                (string) count($group['previous']),
                (string) count($group['new']),
            ];
        }

        return $this->renderTable(['Name', 'Prev errors', 'New errors'], $rows);
    }

    /**
     * @param  array<string, array{previous: array<int, object>, new: array<int, object>}>  $groups
     */
    private function renderSourceGroups(array $groups, bool $omitPreviousDetails): string
    {
        if ($groups === []) {
            return 'No exception logs.';
        }

        $blocks = [];

        foreach ($groups as $sourceKey => $group) {
            $blocks[] = $this->renderSourceGroup($sourceKey, $group['previous'], $group['new'], $omitPreviousDetails);
        }

        return implode(PHP_EOL.PHP_EOL, $blocks);
    }

    /**
     * @param  array<int, object>  $previousErrors
     * @param  array<int, object>  $newErrors
     * @return array<string, array{previous: array<int, object>, new: array<int, object>}>
     */
    private function groupErrorsBySource(array $previousErrors, array $newErrors): array
    {
        $groups = [];

        foreach ($previousErrors as $row) {
            $this->appendSourceGroupRow($groups, $this->sourceKey($row), 'previous', $row);
        }

        foreach ($newErrors as $row) {
            $this->appendSourceGroupRow($groups, $this->sourceKey($row), 'new', $row);
        }

        $localKey = $this->sourceResolver->localKey();

        if (isset($groups[$localKey])) {
            $localGroup = [$localKey => $groups[$localKey]];
            unset($groups[$localKey]);

            return $localGroup + $groups;
        }

        return $groups;
    }

    /**
     * @param  array<string, array{previous: array<int, object>, new: array<int, object>}>  $groups
     * @param  'previous'|'new'  $section
     */
    private function appendSourceGroupRow(array &$groups, string $sourceKey, string $section, object $row): void
    {
        $groups[$sourceKey] ??= [
            'previous' => [],
            'new' => [],
        ];

        $groups[$sourceKey][$section][] = $row;
    }

    /**
     * @param  array<int, object>  $previousErrors
     * @param  array<int, object>  $newErrors
     */
    private function renderSourceGroup(
        string $sourceKey,
        array $previousErrors,
        array $newErrors,
        bool $omitPreviousDetails,
    ): string {
        return implode(PHP_EOL, [
            '['.$sourceKey.']',
            $this->renderPreviousExceptionLines($previousErrors, $omitPreviousDetails),
            self::SECTION_SEPARATOR,
            $this->renderExceptionLines($newErrors, '>', '> No new errors.'),
            self::SECTION_SEPARATOR,
        ]);
    }

    /**
     * @param  array<int, object>  $rows
     */
    private function renderPreviousExceptionLines(array $rows, bool $omitPreviousDetails): string
    {
        if (! $omitPreviousDetails) {
            return $this->renderExceptionLines($rows, '<', '< No previous errors.');
        }

        if ($rows === []) {
            return '< No previous errors.';
        }

        return '< Previous errors omitted ('.count($rows).').';
    }

    /**
     * @param  array<int, object>  $rows
     */
    private function renderExceptionLines(array $rows, string $marker, string $emptyText): string
    {
        if ($rows === []) {
            return $emptyText;
        }

        return implode(PHP_EOL, array_map(
            fn (object $row): string => $this->renderExceptionLine($row, $marker),
            $rows,
        ));
    }

    private function renderExceptionLine(object $row, string $marker): string
    {
        return implode(' ', [
            $marker,
            class_basename((string) $row->name),
            '('.(string) (int) $row->count.')',
        ]);
    }

    /**
     * @param  array<int, string>  $headers
     * @param  array<int, array<int, string>>  $rows
     */
    private function renderTable(array $headers, array $rows): string
    {
        $output = new BufferedOutput;
        $table = new Table($output);

        $table->setHeaders($headers)->setRows($rows)->render();

        return rtrim($output->fetch());
    }

    private function sendDigestToDiscord(string $webhookUrl, string $digest): int
    {
        $chunks = $this->splitDigestForDiscord($digest);
        $chunkCount = count($chunks);

        try {
            foreach ($chunks as $index => $chunk) {
                $response = Http::post($webhookUrl, [
                    'embeds' => [
                        [
                            'title' => $this->discordDigestTitle($index, $chunkCount),
                            'description' => $this->discordEmbedDescription($chunk),
                            'color' => self::DISCORD_EMBED_COLOR,
                        ],
                    ],
                ]);

                if (! $response->successful()) {
                    $this->error('Failed to send exception digest to Discord (HTTP '.$response->status().').');

                    return self::FAILURE;
                }
            }
        } catch (Throwable $throwable) {
            $this->error('Failed to send exception digest to Discord: '.$throwable->getMessage());

            return self::FAILURE;
        }

        $this->info('Exception digest sent to Discord.');

        return self::SUCCESS;
    }

    /**
     * @return array<int, string>
     */
    private function splitDigestForDiscord(string $digest): array
    {
        $chunks = [''];

        foreach (explode(PHP_EOL, $digest) as $line) {
            foreach ($this->splitDigestLine($line) as $linePart) {
                $currentIndex = count($chunks) - 1;
                $current = $chunks[$currentIndex];
                $candidate = $current === ''
                    ? $linePart
                    : $current.PHP_EOL.$linePart;

                if ($current !== '' && strlen($candidate) > self::DISCORD_DIGEST_CHUNK_LIMIT) {
                    $chunks[] = $linePart;

                    continue;
                }

                $chunks[$currentIndex] = $candidate;
            }
        }

        return $chunks;
    }

    private function fitsDiscordEmbedDescription(string $digest): bool
    {
        return strlen($this->discordEmbedDescription($digest)) <= self::DISCORD_EMBED_DESCRIPTION_LIMIT;
    }

    private function discordEmbedDescription(string $digest): string
    {
        return "```text\n".$digest."\n```";
    }

    /**
     * @return array<int, string>
     */
    private function splitDigestLine(string $line): array
    {
        if ($line === '') {
            return [''];
        }

        return str_split($line, self::DISCORD_DIGEST_CHUNK_LIMIT);
    }

    private function discordDigestTitle(int $index, int $chunkCount): string
    {
        if ($chunkCount === 1) {
            return 'Exception Digest';
        }

        return 'Exception Digest ('.($index + 1).'/'.$chunkCount.')';
    }

    private function writeDigest(string $digest): void
    {
        foreach (explode(PHP_EOL, $digest) as $line) {
            $this->line($line);
        }
    }

    private function sourceKey(object $row): string
    {
        $sourceKey = trim((string) $row->source_key);

        return $sourceKey === '' ? $this->sourceResolver->localKey() : $sourceKey;
    }

    private function databaseConnection(DatabaseManager $database)
    {
        $connection = config('exception-viewer.database_connection');

        return $connection === null || $connection === ''
            ? $database->connection()
            : $database->connection($connection);
    }
}
