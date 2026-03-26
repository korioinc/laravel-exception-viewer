<?php

namespace Korioinc\ExceptionViewer\Alarm;

use Illuminate\Contracts\Bus\Dispatcher;
use Korioinc\ExceptionViewer\Alarm\Contracts\ExceptionAlarmChannel;
use Korioinc\ExceptionViewer\Alarm\Jobs\SendExceptionAlarm;

class ExceptionAlarmNotifier
{
    /**
     * @param  iterable<ExceptionAlarmChannel>  $channels
     */
    public function __construct(
        private readonly Dispatcher $dispatcher,
        private readonly iterable $channels,
    ) {}

    public function queueExceptionAlarm(string $message): void
    {
        $this->dispatcher->dispatch(new SendExceptionAlarm($message));
    }

    public function hasEnabledChannels(): bool
    {
        foreach ($this->channels as $channel) {
            if ($channel->isEnabled()) {
                return true;
            }
        }

        return false;
    }

    public function deliverExceptionAlarm(string $message): void
    {
        foreach ($this->channels as $channel) {
            if (! $channel->isEnabled()) {
                continue;
            }

            try {
                $channel->send($message);
            } catch (\Throwable) {
                // Channel delivery must never interrupt queued exception handling.
            }
        }
    }
}
