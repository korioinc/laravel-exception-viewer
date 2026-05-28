<?php

use Illuminate\Support\Facades\Http;
use Korioinc\ExceptionViewer\Forwarding\ExceptionForwardingClient;
use Korioinc\ExceptionViewer\Forwarding\Exceptions\ForwardingConfigurationException;
use Korioinc\ExceptionViewer\Forwarding\Jobs\ForwardExceptionLog;

beforeEach(function () {
    config()->set('exception-viewer.forwarding.endpoint', 'https://central.test/api/exception-viewer/exceptions');
    config()->set('exception-viewer.forwarding.api_key', 'secret-key');
});

function forwardingPayload(array $overrides = []): array
{
    return array_replace_recursive([
        'version' => 1,
        'source' => [
            'key' => 'service-a',
        ],
        'exception' => [
            'key' => str_repeat('a', 64),
            'name' => RuntimeException::class,
            'message' => 'Forward me.',
            'file' => '/var/www/app/Forward.php',
            'line' => 12,
            'raw_exception' => 'Forward me.',
            'count' => 2,
            'latest_at' => '2026-03-25T12:30:00+00:00',
        ],
        'request' => [
            'method' => 'POST',
            'endpoint' => '/api/orders',
            'headers' => '{"authorization":"[MASKED]"}',
            'payload' => '{"order_id":1}',
        ],
        'sent_at' => '2026-03-25T12:31:00+00:00',
    ], $overrides);
}

it('sends the forwarding payload with bearer authentication', function () {
    Http::fake([
        'central.test/*' => Http::response(['accepted' => true], 202),
    ]);

    (new ForwardExceptionLog(forwardingPayload()))->handle(app(ExceptionForwardingClient::class));

    Http::assertSent(function ($request) {
        $data = $request->data();

        return $request->url() === 'https://central.test/api/exception-viewer/exceptions'
            && $request->hasHeader('Authorization', 'Bearer secret-key')
            && ($data['version'] ?? null) === 1
            && ($data['source']['key'] ?? null) === 'service-a'
            && array_keys($data['source'] ?? []) === ['key']
            && ($data['exception']['key'] ?? null) === str_repeat('a', 64);
    });
});

it('does not retry authentication or validation rejections', function (int $status) {
    Http::fake([
        'central.test/*' => Http::response(['message' => 'Rejected'], $status),
    ]);

    expect(fn () => (new ForwardExceptionLog(forwardingPayload()))->handle(app(ExceptionForwardingClient::class)))
        ->not->toThrow(Throwable::class);
})->with([401, 422]);

it('throws for server failures so the queue can retry', function () {
    Http::fake([
        'central.test/*' => Http::response(['message' => 'Unavailable'], 500),
    ]);

    expect(fn () => (new ForwardExceptionLog(forwardingPayload()))->handle(app(ExceptionForwardingClient::class)))
        ->toThrow(RuntimeException::class, 'HTTP 500');
});

it('fails permanently when a queued forwarding job is missing endpoint or api key configuration', function () {
    config()->set('exception-viewer.forwarding.endpoint', '');
    config()->set('exception-viewer.forwarding.api_key', '');

    expect(fn () => (new ForwardExceptionLog(forwardingPayload()))->handle(app(ExceptionForwardingClient::class)))
        ->toThrow(ForwardingConfigurationException::class);
});
