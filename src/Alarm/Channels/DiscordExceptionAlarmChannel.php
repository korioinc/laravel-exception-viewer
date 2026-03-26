<?php

namespace Korioinc\ExceptionViewer\Alarm\Channels;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Korioinc\ExceptionViewer\Alarm\Contracts\ExceptionAlarmChannel;
use Throwable;

class DiscordExceptionAlarmChannel implements ExceptionAlarmChannel
{
    public function isEnabled(): bool
    {
        return $this->webhookUrl() !== '';
    }

    public function send(string $message): void
    {
        $webhookUrl = $this->webhookUrl();

        if ($webhookUrl === '') {
            return;
        }

        $payload = [
            'embeds' => [
                [
                    'title' => config('exception-viewer.notification_title', 'Log Alarm Notification'),
                    'description' => $message,
                    'color' => 16711680,
                    'fields' => [
                        [
                            'name' => 'Priority',
                            'value' => 'High',
                            'inline' => true,
                        ],
                    ],
                ],
            ],
        ];

        try {
            $response = Http::post($webhookUrl, $payload);

            if (! $response->successful()) {
                Log::info('ExceptionViewer::sendDiscordNotification->error: '.$response->body());
            }
        } catch (Throwable $throwable) {
            Log::info('ExceptionViewer::sendDiscordNotification->error: '.$throwable->getMessage());
        }
    }

    private function webhookUrl(): string
    {
        return trim((string) config('exception-viewer.discord_webhook_url', ''));
    }
}
