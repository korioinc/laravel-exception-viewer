<?php

namespace Korioinc\ExceptionViewer\Alarm\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Korioinc\ExceptionViewer\Alarm\ExceptionAlarmNotifier;

class SendExceptionAlarm implements ShouldQueue
{
    public function __construct(
        public readonly string $message,
    ) {}

    public function handle(ExceptionAlarmNotifier $exceptionAlarmNotifier): void
    {
        $exceptionAlarmNotifier->deliverExceptionAlarm($this->message);
    }
}
