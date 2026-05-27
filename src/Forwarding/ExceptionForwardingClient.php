<?php

namespace Korioinc\ExceptionViewer\Forwarding;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Korioinc\ExceptionViewer\Forwarding\Exceptions\ForwardingConfigurationException;
use RuntimeException;

class ExceptionForwardingClient
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function send(array $payload): void
    {
        $endpoint = trim((string) config('exception-viewer.forwarding.endpoint', ''));
        $apiKey = trim((string) config('exception-viewer.forwarding.api_key', ''));

        if ($endpoint === '' || $apiKey === '') {
            throw new ForwardingConfigurationException('Exception Viewer forwarding endpoint and API key must be configured.');
        }

        $response = Http::timeout($this->timeout())
            ->withToken($apiKey)
            ->acceptJson()
            ->asJson()
            ->post($endpoint, $payload);

        if ($response->status() === 202) {
            return;
        }

        if (in_array($response->status(), [401, 422], true)) {
            Log::warning('Exception Viewer forwarding rejected by central receiver.', [
                'status' => $response->status(),
            ]);

            return;
        }

        throw new RuntimeException('Exception Viewer forwarding failed with HTTP '.$response->status().'.');
    }

    private function timeout(): float
    {
        return max(0.1, (float) config('exception-viewer.forwarding.timeout', 2));
    }
}
