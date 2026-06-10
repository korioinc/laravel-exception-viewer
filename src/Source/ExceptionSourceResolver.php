<?php

namespace Korioinc\ExceptionViewer\Source;

use Illuminate\Contracts\Config\Repository as ConfigRepository;

class ExceptionSourceResolver
{
    private const DEFAULT_LOCAL_KEY = 'local-app';

    private const DEFAULT_LOCAL_LABEL = 'Local App';

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

        return self::DEFAULT_LOCAL_KEY;
    }

    public function localLabel(): string
    {
        $configured = trim((string) $this->config->get('exception-viewer.source.label', self::DEFAULT_LOCAL_LABEL));

        if ($configured !== '') {
            return $configured;
        }

        return self::DEFAULT_LOCAL_LABEL;
    }

    public function forwardingKey(): string
    {
        return $this->configuredKey();
    }
}
