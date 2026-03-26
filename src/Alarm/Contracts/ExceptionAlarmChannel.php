<?php

namespace Korioinc\ExceptionViewer\Alarm\Contracts;

interface ExceptionAlarmChannel
{
    public function isEnabled(): bool;

    public function send(string $message): void;
}
