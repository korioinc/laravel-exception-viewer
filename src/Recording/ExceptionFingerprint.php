<?php

namespace Korioinc\ExceptionViewer\Recording;

use Throwable;

class ExceptionFingerprint
{
    public function make(Throwable $throwable): string
    {
        return hash('sha256', json_encode([
            'class' => $throwable::class,
            'message' => $throwable->getMessage(),
            'file' => $throwable->getFile(),
            'line' => $throwable->getLine(),
            'trace' => $this->normalizeTrace($throwable),
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    private function normalizeTrace(Throwable $throwable): array
    {
        return array_map(static function (array $frame): array {
            return [
                'file' => $frame['file'] ?? null,
                'class' => $frame['class'] ?? null,
                'type' => $frame['type'] ?? null,
                'function' => $frame['function'],
            ];
        }, array_slice($throwable->getTrace(), 0, 3));
    }
}
