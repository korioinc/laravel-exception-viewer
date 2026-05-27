<?php

namespace Korioinc\ExceptionViewer\Source;

use Illuminate\Contracts\Config\Repository as ConfigRepository;

class ExceptionSourceResolver
{
    public function __construct(
        private readonly ConfigRepository $config,
    ) {}

    public function configuredKey(): string
    {
        return trim((string) $this->config->get('exception-viewer.source.key', ''));
    }

    public function localKey(): string
    {
        $configured = $this->configuredKey();

        if ($configured !== '') {
            return $configured;
        }

        return 'local-app';
    }

    public function forwardingKey(): string
    {
        return $this->configuredKey();
    }
}
