<?php

namespace Korioinc\ExceptionViewer\Viewer;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ExceptionEntryFormatter
{
    public function summarize(object $exception, int $index): array
    {
        return [
            'dom_id' => 'exception-'.($index + 1).'-'.Str::lower(substr($exception->key, 0, 8)),
            'key' => $exception->key,
            'short_key' => substr($exception->key, 0, 8),
            'name' => $exception->name,
            'message' => $exception->message,
            'location' => $exception->file.':'.$exception->line,
            'count' => (int) $exception->count,
            'latest_at' => $this->formatTimestamp($exception->latest_at),
            'request_method' => $exception->request_method ?? 'CLI',
            'request_endpoint' => $exception->request_endpoint ?? 'No HTTP request',
            'request_headers' => $this->formatStructuredJson($exception->request_headers),
            'request_payload' => $this->formatStructuredJson($exception->request_payload),
            'copy_markdown' => $this->toCopyMarkdown($exception),
            'markdown' => $this->toMarkdown($exception),
            'raw_exception' => trim((string) $exception->raw_exception),
        ];
    }

    public function toCopyMarkdown(object $exception): string
    {
        return $this->renderMarkdown($exception, includeExtendedMeta: false);
    }

    public function toMarkdown(object $exception): string
    {
        return $this->renderMarkdown($exception, includeExtendedMeta: true);
    }

    public function toMarkdownCollection(iterable $exceptions): string
    {
        return $this->renderMarkdownCollection($exceptions, fn (object $exception): string => $this->toMarkdown($exception));
    }

    public function toCopyMarkdownCollection(iterable $exceptions): string
    {
        return $this->renderMarkdownCollection($exceptions, fn (object $exception): string => $this->toCopyMarkdown($exception));
    }

    private function renderMarkdownCollection(iterable $exceptions, callable $formatter): string
    {
        $sections = Collection::make($exceptions)
            ->map(fn (object $exception): string => $formatter($exception))
            ->filter(fn (string $markdown): bool => trim($markdown) !== '')
            ->values();

        if ($sections->isEmpty()) {
            return "# Exceptions\n\nNo entries.\n";
        }

        return $sections->implode("\n\n---\n\n");
    }

    private function renderMarkdown(object $exception, bool $includeExtendedMeta): string
    {
        $lines = [
            '# Exception',
            '',
        ];

        if ($includeExtendedMeta) {
            $lines[] = '- Key: `'.$exception->key.'`';
        }

        $lines[] = '- Name: `'.$exception->name.'`';
        $lines[] = '- Message: '.$this->inlineValue($exception->message);
        $lines[] = '- File: `'.$exception->file.'`';
        $lines[] = '- Line: '.(string) $exception->line;

        if ($includeExtendedMeta) {
            $lines[] = '- Count: '.(string) $exception->count;
            $lines[] = '- Latest At: '.$this->formatTimestamp($exception->latest_at);
        }

        $requestBlock = $this->requestSummaryBlock($exception);
        $headersBlock = $this->jsonBlock('Headers', $exception->request_headers);
        $payloadBlock = $this->jsonBlock('Payload', $exception->request_payload);
        $contextBlock = $this->textBlock('Context', trim((string) $exception->raw_exception));

        foreach ([$requestBlock, $headersBlock, $payloadBlock, $contextBlock] as $block) {
            if ($block === []) {
                continue;
            }

            $lines[] = '';
            array_push($lines, ...$block);
        }

        return implode("\n", $lines);
    }

    private function requestSummaryBlock(object $exception): array
    {
        $method = $this->nullableInlineValue($exception->request_method ?? null);
        $endpoint = $this->nullableInlineValue($exception->request_endpoint ?? null);

        if ($method === null && $endpoint === null) {
            return [];
        }

        $lines = ['## Request', ''];

        if ($method !== null) {
            $lines[] = '- Method: '.$method;
        }

        if ($endpoint !== null) {
            $lines[] = '- Endpoint: '.$endpoint;
        }

        return $lines;
    }

    private function jsonBlock(string $title, ?string $value): array
    {
        $value = $this->rawStructuredJsonOrNull($value);

        if ($value === null) {
            return [];
        }

        return $this->fencedBlock($title, 'json', $value);
    }

    private function textBlock(string $title, ?string $value): array
    {
        $value = trim((string) $value);

        if ($value === '') {
            return [];
        }

        return $this->fencedBlock($title, 'text', $value);
    }

    private function fencedBlock(string $title, string $language, string $value): array
    {
        $fence = $this->tildeFence($value);

        return [
            '## '.$title,
            '',
            $fence.$language,
            $value,
            $fence,
        ];
    }

    private function tildeFence(string $value): string
    {
        preg_match_all('/~+/', $value, $matches);

        $maxRunLength = max(array_map(static fn (string $run): int => strlen($run), $matches[0] ?: ['']));

        return str_repeat('~', max(3, $maxRunLength + 1));
    }

    private function formatStructuredJson(?string $value): string
    {
        if ($value === null || trim($value) === '') {
            return 'N/A';
        }

        try {
            $decoded = json_decode($value, true, flags: JSON_THROW_ON_ERROR);

            return json_encode($decoded, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } catch (\JsonException) {
            return $value;
        }
    }

    private function rawStructuredJsonOrNull(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return trim($value);
    }

    private function formatTimestamp(mixed $value): string
    {
        return Carbon::parse($value)->format('Y-m-d H:i:s');
    }

    private function inlineValue(?string $value): string
    {
        $value = trim((string) $value);

        return $value === '' ? 'N/A' : $value;
    }

    private function nullableInlineValue(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
