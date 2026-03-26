<?php

namespace Korioinc\ExceptionViewer\Alarm;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Throwable;

class ExceptionAlarmHandler
{
    private const LAST_NOTIFICATION_CACHE_KEY = 'exception_viewer_alarm_last_notification';

    public function __construct(
        private readonly ExceptionAlarmNotifier $exceptionAlarmNotifier,
    ) {}

    public function handle(Throwable $throwable, ?string $fingerprintKey): void
    {
        if (! config('exception-viewer.enabled', true)) {
            return;
        }

        if (! config('exception-viewer.alarm_enabled', false)) {
            return;
        }

        if ($fingerprintKey === null || $fingerprintKey === '') {
            return;
        }

        try {
            if (! $this->exceptionAlarmNotifier->hasEnabledChannels()) {
                return;
            }

            $now = now();
            $alarmCacheKey = $this->alarmCacheKey($fingerprintKey);
            $logTimeFrame = $this->logTimeFrame();
            $logPerTimeFrame = $this->logPerTimeFrame();
            $delayBetweenAlarms = $this->delayBetweenAlarms();

            if ($this->isCoolingDown($alarmCacheKey)) {
                return;
            }

            $this->exceptionAlarmNotifier->queueExceptionAlarm($this->message($throwable, $fingerprintKey));

            if ($this->recordNotificationAndCount($alarmCacheKey, $now, $logTimeFrame) < $logPerTimeFrame) {
                return;
            }

            if ($delayBetweenAlarms === 0) {
                return;
            }

            Cache::put(
                $this->lastNotificationCacheKey($alarmCacheKey),
                $now->getTimestamp(),
                $now->copy()->addMinutes($delayBetweenAlarms),
            );

            $this->clearRecentNotificationCounters($alarmCacheKey, $now, $logTimeFrame);
        } catch (Throwable) {
            // Alarming must never interrupt Laravel's native exception flow.
        }
    }

    private function message(Throwable $throwable, string $fingerprintKey): string
    {
        $message = config('exception-viewer.notification_message', '');
        $message = $message === '' ? $this->exceptionMessage($throwable) : $message;

        return $message."\r\n\r\n".$this->detailLink($fingerprintKey);
    }

    private function exceptionMessage(Throwable $throwable): string
    {
        return implode("\r\n", [
            'LOG_LEVEL: error',
            'LOG_MESSAGE: '.$throwable->getMessage(),
            'LOG_FILE: '.$throwable->getFile(),
            'LOG_LINE: '.$throwable->getLine(),
        ]);
    }

    private function alarmCacheKey(string $fingerprintKey): string
    {
        return 'exception_viewer_alarm_'.$fingerprintKey;
    }

    private function recordNotificationAndCount(string $alarmCacheKey, Carbon $now, int $logTimeFrame): int
    {
        $currentTimestamp = $now->getTimestamp();
        $windowInSeconds = $logTimeFrame * 60;
        $counterKey = $this->counterCacheKey($alarmCacheKey, $currentTimestamp);

        Cache::add($counterKey, 0, $now->copy()->addSeconds($windowInSeconds));
        Cache::increment($counterKey);

        $counterKeys = [];

        for ($timestamp = $currentTimestamp - $windowInSeconds + 1; $timestamp <= $currentTimestamp; $timestamp++) {
            $counterKeys[] = $this->counterCacheKey($alarmCacheKey, $timestamp);
        }

        return array_sum(array_map(
            static fn (mixed $value): int => (int) $value,
            Cache::many($counterKeys),
        ));
    }

    private function isCoolingDown(string $alarmCacheKey): bool
    {
        return Cache::has($this->lastNotificationCacheKey($alarmCacheKey));
    }

    private function counterCacheKey(string $alarmCacheKey, int $timestamp): string
    {
        return $alarmCacheKey.'_count_'.$timestamp;
    }

    private function clearRecentNotificationCounters(string $alarmCacheKey, Carbon $now, int $logTimeFrame): void
    {
        $currentTimestamp = $now->getTimestamp();
        $windowInSeconds = $logTimeFrame * 60;

        for ($timestamp = $currentTimestamp - $windowInSeconds + 1; $timestamp <= $currentTimestamp; $timestamp++) {
            Cache::forget($this->counterCacheKey($alarmCacheKey, $timestamp));
        }
    }

    private function detailUrl(string $fingerprintKey): string
    {
        return route('exception-viewer.show', ['key' => $fingerprintKey]);
    }

    private function detailLink(string $fingerprintKey): string
    {
        return '[Open in Viewer]('.$this->detailUrl($fingerprintKey).')';
    }

    private function lastNotificationCacheKey(string $alarmCacheKey): string
    {
        return self::LAST_NOTIFICATION_CACHE_KEY.'_'.$alarmCacheKey;
    }

    private function logTimeFrame(): int
    {
        return max(1, (int) config('exception-viewer.log_time_frame', 3));
    }

    private function logPerTimeFrame(): int
    {
        return max(1, (int) config('exception-viewer.log_per_time_frame', 2));
    }

    private function delayBetweenAlarms(): int
    {
        return max(0, (int) config('exception-viewer.delay_between_alarms', 5));
    }
}
