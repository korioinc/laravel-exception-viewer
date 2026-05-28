<?php

use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Korioinc\ExceptionViewer\Alarm\Contracts\ExceptionAlarmChannel;
use Korioinc\ExceptionViewer\Alarm\ExceptionAlarmHandler;
use Korioinc\ExceptionViewer\Alarm\ExceptionAlarmNotifier;
use Korioinc\ExceptionViewer\Alarm\Jobs\SendExceptionAlarm;
use Korioinc\ExceptionViewer\Context\QueueContextStore;
use Korioinc\ExceptionViewer\Forwarding\Jobs\ForwardExceptionLog;
use Korioinc\ExceptionViewer\Tests\Fakes\EncryptedThrowingQueuedJob;
use Korioinc\ExceptionViewer\Tests\Fakes\NamedQueueJob;
use Korioinc\ExceptionViewer\Tests\Fakes\NestedQueuedJob;
use Korioinc\ExceptionViewer\Tests\Fakes\PayloadExplodingQueueJob;
use Korioinc\ExceptionViewer\Tests\Fakes\ThrowingQueuedJob;

beforeEach(function () {
    Cache::flush();
    DB::table('exception_logs')->delete();
});

function aggregatedThrowable(): Throwable
{
    try {
        throwRepeatedException();
    } catch (Throwable $throwable) {
        return $throwable;
    }
}

function firstFingerprintThrowable(): Throwable
{
    try {
        throwSameMessageFromFirstLocation();
    } catch (Throwable $throwable) {
        return $throwable;
    }
}

function secondFingerprintThrowable(): Throwable
{
    try {
        throwSameMessageFromSecondLocation();
    } catch (Throwable $throwable) {
        return $throwable;
    }
}

function throwRepeatedException(): never
{
    throw new RuntimeException('Repeated exception for aggregation');
}

function throwSameMessageFromFirstLocation(): never
{
    throw new RuntimeException('Same message, different fingerprint');
}

function throwSameMessageFromSecondLocation(): never
{
    throw new RuntimeException('Same message, different fingerprint');
}

it('aggregates the same exception into one row and updates latest metadata', function () {
    $firstOccurrence = Carbon::parse('2026-03-22 10:00:00');
    $secondOccurrence = $firstOccurrence->copy()->addMinutes(5);

    Carbon::setTestNow($firstOccurrence);
    report(aggregatedThrowable());

    Carbon::setTestNow($secondOccurrence);
    report(aggregatedThrowable());

    Carbon::setTestNow();

    $rows = DB::table('exception_logs')->get();

    expect($rows)->toHaveCount(1);

    $row = $rows->first();

    expect($row->count)->toBe(2)
        ->and($row->name)->toBe(RuntimeException::class)
        ->and($row->message)->toBe('Repeated exception for aggregation')
        ->and($row->request_method)->toBeNull()
        ->and($row->request_endpoint)->toBeNull()
        ->and($row->raw_exception)->toContain('Repeated exception for aggregation')
        ->and(Carbon::parse($row->latest_at)->equalTo($secondOccurrence))->toBeTrue();
});

it('creates different rows when the fingerprint changes', function () {
    report(firstFingerprintThrowable());
    report(secondFingerprintThrowable());

    $rows = DB::table('exception_logs')->get();

    expect($rows)->toHaveCount(2)
        ->and($rows->pluck('key')->unique())->toHaveCount(2);
});

it('stores masked and truncated request context for http exceptions', function () {
    config()->set('exception-viewer.request_context.max_headers_size', 80);
    config()->set('exception-viewer.request_context.max_payload_size', 80);

    Route::post('/_exception-viewer/test', function () {
        report(aggregatedThrowable());

        return response()->json(['ok' => true]);
    });

    $this
        ->withHeaders([
            'Authorization' => 'Bearer top-secret',
            'X-Api-Key' => 'secret-key',
            'X-Extra' => str_repeat('x', 100),
        ])
        ->postJson('/_exception-viewer/test?via=http', [
            'password' => 'secret-password',
            'token' => 'secret-token',
            'nested' => [
                'secret' => 'hidden',
            ],
            'notes' => str_repeat('n', 100),
        ])
        ->assertOk();

    $row = DB::table('exception_logs')->first();

    $headers = json_decode($row->request_headers, true, flags: JSON_THROW_ON_ERROR);
    $payload = json_decode($row->request_payload, true, flags: JSON_THROW_ON_ERROR);

    expect($row->request_method)->toBe('POST')
        ->and($row->request_endpoint)->toContain('/_exception-viewer/test?via=http')
        ->and($headers['truncated'] ?? false)->toBeTrue()
        ->and($payload['truncated'] ?? false)->toBeTrue();
});

it('does not truncate request context by default', function () {
    Route::post('/_exception-viewer/unlimited', function () {
        report(aggregatedThrowable());

        return response()->json(['ok' => true]);
    });

    $notes = str_repeat('n', 50_000);
    $headerValue = str_repeat('x', 20_000);

    $this
        ->withHeaders([
            'Authorization' => 'Bearer top-secret',
            'X-Api-Key' => 'secret-key',
            'X-Extra' => $headerValue,
        ])
        ->postJson('/_exception-viewer/unlimited', [
            'password' => 'secret-password',
            'token' => 'secret-token',
            'nested' => [
                'secret' => 'nested-secret',
            ],
            'notes' => $notes,
        ])
        ->assertOk();

    $row = DB::table('exception_logs')->first();

    $headers = json_decode($row->request_headers, true, flags: JSON_THROW_ON_ERROR);
    $payload = json_decode($row->request_payload, true, flags: JSON_THROW_ON_ERROR);

    expect($headers['truncated'] ?? false)->toBeFalse()
        ->and($payload['truncated'] ?? false)->toBeFalse()
        ->and($headers['authorization'] ?? null)->toBe('[MASKED]')
        ->and($headers['x-api-key'] ?? null)->toBe('[MASKED]')
        ->and($headers['x-extra'][0] ?? null)->toBe($headerValue)
        ->and($payload['password'])->toBe('[MASKED]')
        ->and($payload['token'])->toBe('secret-token')
        ->and($payload['nested']['secret'])->toBe('nested-secret')
        ->and($payload['notes'])->toBe($notes);
});

it('records the exception when request payload contains malformed utf8', function () {
    app()->instance('request', Request::create('/_exception-viewer/invalid-utf8', 'POST', [
        'bad' => "\xB1",
        'password' => "\xB1",
    ]));

    report(aggregatedThrowable());

    $row = DB::table('exception_logs')->first();
    $payload = json_decode($row->request_payload, true, flags: JSON_THROW_ON_ERROR);

    expect($row)->not->toBeNull()
        ->and($payload['bad'])->toBe("\u{FFFD}")
        ->and($payload['password'])->toBe('[MASKED]');
});

it('stores queued job context for console job exceptions', function () {
    config()->set('queue.default', 'sync');

    try {
        dispatch_sync(new ThrowingQueuedJob('order-123', 'super-secret'));
    } catch (Throwable $throwable) {
        report($throwable);
    }

    $row = DB::table('exception_logs')->first();
    $headers = json_decode($row->request_headers, true, flags: JSON_THROW_ON_ERROR);
    $payload = json_decode($row->request_payload, true, flags: JSON_THROW_ON_ERROR);

    expect($row->request_method)->toBe('JOB')
        ->and($row->request_endpoint)->toBe(ThrowingQueuedJob::class)
        ->and($headers['queue'])->toBe('sync')
        ->and($headers['attempts'])->toBe(1)
        ->and($headers['job_id'])->toBe('')
        ->and($headers['job_name'])->toBe(ThrowingQueuedJob::class)
        ->and($payload['orderId'])->toBe('order-123')
        ->and($payload['password'])->toBe('[MASKED]')
        ->and($headers)->not->toHaveKey('connection')
        ->and($payload)->not->toHaveKey('connection')
        ->and($payload)->not->toHaveKey('queue');
});

it('stores queued job payload for encrypted jobs', function () {
    config()->set('queue.default', 'sync');

    try {
        dispatch_sync(new EncryptedThrowingQueuedJob('order-789', 'super-secret'));
    } catch (Throwable $throwable) {
        report($throwable);
    }

    $row = DB::table('exception_logs')->where('message', 'Encrypted queued job exploded')->first();
    $payload = json_decode($row->request_payload, true, flags: JSON_THROW_ON_ERROR);

    expect($row->request_method)->toBe('JOB')
        ->and($row->request_endpoint)->toBe(EncryptedThrowingQueuedJob::class)
        ->and($payload['orderId'])->toBe('order-789')
        ->and($payload['password'])->toBe('[MASKED]');
});

it('keeps outer queued job context after an inner queued job exception is reported', function () {
    config()->set('queue.default', 'sync');

    try {
        dispatch_sync(new NestedQueuedJob('outer-123', 'outer-secret'));
    } catch (Throwable $throwable) {
        report($throwable);
    }

    $innerRow = DB::table('exception_logs')->where('message', 'Queued job exploded')->first();
    $outerRow = DB::table('exception_logs')->where('message', 'Outer queued job exploded')->first();
    $innerPayload = json_decode($innerRow->request_payload, true, flags: JSON_THROW_ON_ERROR);
    $outerPayload = json_decode($outerRow->request_payload, true, flags: JSON_THROW_ON_ERROR);

    expect($innerRow->request_endpoint)->toBe(ThrowingQueuedJob::class)
        ->and($innerPayload['orderId'])->toBe('inner-456')
        ->and($innerPayload)->not->toHaveKey('connection')
        ->and($innerPayload)->not->toHaveKey('queue')
        ->and($outerRow->request_method)->toBe('JOB')
        ->and($outerRow->request_endpoint)->toBe(NestedQueuedJob::class)
        ->and($outerPayload['orderId'])->toBe('outer-123')
        ->and($outerPayload['password'])->toBe('[MASKED]')
        ->and($outerPayload)->not->toHaveKey('connection')
        ->and($outerPayload)->not->toHaveKey('queue');
});

it('does not resolve queued job payload during capture', function () {
    $store = app(QueueContextStore::class);

    $store->capture(new PayloadExplodingQueueJob);

    expect(true)->toBeTrue();
});

it('does not fail the application flow when storage fails or is disabled', function () {
    config()->set('exception-viewer.database_connection', 'missing');

    expect(fn () => report(aggregatedThrowable()))->not->toThrow(Throwable::class);

    config()->set('exception-viewer.database_connection', 'testing');
    config()->set('exception-viewer.enabled', false);

    report(aggregatedThrowable());

    expect(DB::table('exception_logs')->count())->toBe(0);
});

it('does not queue forwarding when forwarding is disabled', function () {
    Queue::fake([ForwardExceptionLog::class]);

    report(aggregatedThrowable());

    Queue::assertNotPushed(ForwardExceptionLog::class);
});

it('queues forwarding for the persisted exception snapshot when configured', function () {
    config()->set('exception-viewer.source.key', 'service-a');
    config()->set('exception-viewer.forwarding.enabled', true);
    config()->set('exception-viewer.forwarding.mode', 'queue');
    config()->set('exception-viewer.forwarding.endpoint', 'https://central.test/api/exception-viewer/exceptions');
    config()->set('exception-viewer.forwarding.api_key', 'secret-key');

    Queue::fake([ForwardExceptionLog::class]);

    report(aggregatedThrowable());

    Queue::assertPushed(ForwardExceptionLog::class, function (ForwardExceptionLog $job) {
        return $job->payload['version'] === 1
            && $job->payload['source']['key'] === 'service-a'
            && array_keys($job->payload['source']) === ['key']
            && $job->payload['exception']['key'] === DB::table('exception_logs')->value('key')
            && $job->payload['exception']['count'] === 1
            && $job->payload['request']['headers'] === null;
    });
});

it('sends forwarding synchronously by default when forwarding is configured', function () {
    config()->set('exception-viewer.source.key', 'service-a');
    config()->set('exception-viewer.forwarding.enabled', true);
    config()->set('exception-viewer.forwarding.endpoint', 'https://central.test/api/exception-viewer/exceptions');
    config()->set('exception-viewer.forwarding.api_key', 'secret-key');

    Http::fake([
        'central.test/*' => Http::response(['accepted' => true], 202),
    ]);
    Queue::fake([ForwardExceptionLog::class]);

    report(aggregatedThrowable());

    Queue::assertNotPushed(ForwardExceptionLog::class);
    Http::assertSent(function ($request) {
        $data = $request->data();

        return $request->url() === 'https://central.test/api/exception-viewer/exceptions'
            && $request->hasHeader('Authorization', 'Bearer secret-key')
            && ($data['version'] ?? null) === 1
            && ($data['source']['key'] ?? null) === 'service-a'
            && ($data['exception']['key'] ?? null) === DB::table('exception_logs')->value('key');
    });
});

it('sends forwarding synchronously when an older published config has no forwarding mode', function () {
    config()->set('exception-viewer.source.key', 'service-a');
    config()->set('exception-viewer.forwarding', [
        'enabled' => true,
        'endpoint' => 'https://central.test/api/exception-viewer/exceptions',
        'api_key' => 'secret-key',
        'queue' => null,
        'timeout' => 2,
        'tries' => 3,
        'backoff' => 60,
    ]);

    Http::fake([
        'central.test/*' => Http::response(['accepted' => true], 202),
    ]);
    Queue::fake([ForwardExceptionLog::class]);

    report(aggregatedThrowable());

    Queue::assertNotPushed(ForwardExceptionLog::class);
    Http::assertSentCount(1);
});

it('skips forwarding when local persistence fails', function () {
    config()->set('exception-viewer.database_connection', 'missing');
    config()->set('exception-viewer.source.key', 'service-a');
    config()->set('exception-viewer.forwarding.enabled', true);
    config()->set('exception-viewer.forwarding.endpoint', 'https://central.test/api/exception-viewer/exceptions');
    config()->set('exception-viewer.forwarding.api_key', 'secret-key');

    Queue::fake([ForwardExceptionLog::class]);

    expect(fn () => report(aggregatedThrowable()))->not->toThrow(Throwable::class);

    Queue::assertNotPushed(ForwardExceptionLog::class);
});

it('records forwarding job failures without forwarding them again', function () {
    config()->set('exception-viewer.source.key', 'service-a');
    config()->set('exception-viewer.forwarding.enabled', true);
    config()->set('exception-viewer.forwarding.endpoint', 'https://central.test/api/exception-viewer/exceptions');
    config()->set('exception-viewer.forwarding.api_key', 'secret-key');

    Queue::fake([ForwardExceptionLog::class]);

    $store = app(QueueContextStore::class);
    $store->capture(new NamedQueueJob(ForwardExceptionLog::class));

    try {
        report(new RuntimeException('Exception Viewer forwarding failed with HTTP 500.'));
    } finally {
        $store->clear();
    }

    Queue::assertNotPushed(ForwardExceptionLog::class);

    $row = DB::table('exception_logs')->first();

    expect($row)->not->toBeNull()
        ->and($row->message)->toBe('Exception Viewer forwarding failed with HTTP 500.')
        ->and($row->request_method)->toBe('JOB')
        ->and($row->request_endpoint)->toBe(ForwardExceptionLog::class);
});

it('dispatches a discord alarm job even when storage fails', function () {
    config()->set('exception-viewer.database_connection', 'missing');
    config()->set('exception-viewer.alarm_enabled', true);
    config()->set('exception-viewer.discord_webhook_url', 'https://discord.test/webhook');
    config()->set('exception-viewer.log_per_time_frame', 1);

    Queue::fake([SendExceptionAlarm::class]);

    report(aggregatedThrowable());

    Queue::assertPushed(SendExceptionAlarm::class, 1);
});

it('dispatches an exception alarm job when a non-discord channel is enabled', function () {
    config()->set('exception-viewer.alarm_enabled', true);
    config()->set('exception-viewer.discord_webhook_url', '');
    config()->set('exception-viewer.log_per_time_frame', 1);

    $channel = new class implements ExceptionAlarmChannel
    {
        public function isEnabled(): bool
        {
            return true;
        }

        public function send(string $message): void {}
    };

    app()->instance(ExceptionAlarmNotifier::class, new ExceptionAlarmNotifier(
        app(Dispatcher::class),
        [$channel],
    ));

    Queue::fake([SendExceptionAlarm::class]);

    report(aggregatedThrowable());

    Queue::assertPushed(SendExceptionAlarm::class, 1);
});

it('does not throw when checking whether alarm channels are enabled fails', function () {
    config()->set('exception-viewer.enabled', true);
    config()->set('exception-viewer.alarm_enabled', true);

    $notifier = new ExceptionAlarmNotifier(
        app(Dispatcher::class),
        [new class implements ExceptionAlarmChannel
        {
            public function isEnabled(): bool
            {
                throw new RuntimeException('Channel state unavailable');
            }

            public function send(string $message): void {}
        }],
    );

    expect(fn () => $notifier->hasEnabledChannels())
        ->toThrow(RuntimeException::class, 'Channel state unavailable');

    $handler = new ExceptionAlarmHandler($notifier);

    $thrown = null;

    try {
        $handler->handle(aggregatedThrowable(), 'fingerprint-key');
    } catch (Throwable $exception) {
        $thrown = $exception;
    }

    expect($thrown)->toBeNull();
});

it('does not throw when alarm cache lookups fail', function () {
    config()->set('exception-viewer.alarm_enabled', true);
    config()->set('exception-viewer.discord_webhook_url', 'https://discord.test/webhook');

    $originalCache = Cache::getFacadeRoot();
    Cache::swap(new class
    {
        public function has(string $key): bool
        {
            throw new RuntimeException('Cache unavailable');
        }

        public function add(string $key, mixed $value, mixed $ttl): bool
        {
            throw new RuntimeException('Cache unavailable');
        }

        public function increment(string $key, mixed $value = 1): int
        {
            throw new RuntimeException('Cache unavailable');
        }

        public function many(array $keys): array
        {
            throw new RuntimeException('Cache unavailable');
        }

        public function put(string $key, mixed $value, mixed $ttl): bool
        {
            throw new RuntimeException('Cache unavailable');
        }

        public function forget(string $key): bool
        {
            throw new RuntimeException('Cache unavailable');
        }
    });

    try {
        $thrown = null;

        try {
            app(ExceptionAlarmHandler::class)->handle(aggregatedThrowable(), 'fingerprint-key');
        } catch (Throwable $exception) {
            $thrown = $exception;
        }

        expect($thrown)->toBeNull();
    } finally {
        Cache::swap($originalCache);
    }
});

it('dispatches a discord alarm job when alarming is enabled', function () {
    config()->set('exception-viewer.alarm_enabled', true);
    config()->set('exception-viewer.discord_webhook_url', 'https://discord.test/webhook');
    config()->set('exception-viewer.log_per_time_frame', 1);
    config()->set('exception-viewer.delay_between_alarms', 5);

    Queue::fake([SendExceptionAlarm::class]);

    report(aggregatedThrowable());

    $detailUrl = route('exception-viewer.show', [
        'key' => DB::table('exception_logs')->value('key'),
    ]);
    $detailLink = '[Open in Viewer]('.$detailUrl.')';

    Queue::assertPushed(SendExceptionAlarm::class, function (SendExceptionAlarm $job) use ($detailLink) {
        return str_contains($job->message, 'LOG_LEVEL: error')
            && str_contains($job->message, 'LOG_MESSAGE: Repeated exception for aggregation')
            && str_contains($job->message, 'LOG_FILE:')
            && str_contains($job->message, 'LOG_LINE:')
            && str_contains($job->message, $detailLink);
    });
});

it('includes the viewer link in discord alarms even when the detail route is blocked in production', function () {
    $this->app['env'] = 'production';

    config()->set('exception-viewer.alarm_enabled', true);
    config()->set('exception-viewer.discord_webhook_url', 'https://discord.test/webhook');
    config()->set('exception-viewer.log_per_time_frame', 1);

    Queue::fake([SendExceptionAlarm::class]);

    report(aggregatedThrowable());

    $detailUrl = route('exception-viewer.show', [
        'key' => DB::table('exception_logs')->value('key'),
    ]);
    $detailLink = '[Open in Viewer]('.$detailUrl.')';

    Queue::assertPushed(SendExceptionAlarm::class, function (SendExceptionAlarm $job) use ($detailLink) {
        return str_contains($job->message, $detailLink);
    });
});

it('queued discord alarm job sends the expected payload', function () {
    config()->set('exception-viewer.discord_webhook_url', 'https://discord.test/webhook');
    config()->set('exception-viewer.log_per_time_frame', 1);

    Http::fake();

    $job = new SendExceptionAlarm("LOG_LEVEL: error\r\nLOG_MESSAGE: Repeated exception for aggregation\r\nLOG_FILE: /var/www/app/CheckoutService.php\r\nLOG_LINE: 184\r\n\r\n[Open in Viewer](https://viewer.test/exception-viewer/1234567890abcdef1234567890abcdef1234567890abcdef1234567890abcdef)");

    $job->handle(app(ExceptionAlarmNotifier::class));

    Http::assertSent(function ($request) {
        $data = $request->data();
        $embed = $data['embeds'][0] ?? [];
        $priorityField = $embed['fields'][0] ?? [];

        return $request->url() === 'https://discord.test/webhook'
            && ($embed['title'] ?? null) === 'Log Alarm Notification'
            && ($embed['description'] ?? null) === "LOG_LEVEL: error\r\nLOG_MESSAGE: Repeated exception for aggregation\r\nLOG_FILE: /var/www/app/CheckoutService.php\r\nLOG_LINE: 184\r\n\r\n[Open in Viewer](https://viewer.test/exception-viewer/1234567890abcdef1234567890abcdef1234567890abcdef1234567890abcdef)"
            && ($embed['color'] ?? null) === 16711680
            && ($priorityField['name'] ?? null) === 'Priority'
            && ($priorityField['value'] ?? null) === 'High'
            && ($priorityField['inline'] ?? null) === true;
    });
});

it('dispatches a discord alarm job by default when the webhook is configured', function () {
    config()->set('exception-viewer.discord_webhook_url', 'https://discord.test/webhook');

    Queue::fake([SendExceptionAlarm::class]);

    report(aggregatedThrowable());
    report(aggregatedThrowable());
    report(aggregatedThrowable());

    Queue::assertPushed(SendExceptionAlarm::class, 2);
});

it('does not dispatch a discord alarm job when alarming is disabled or webhook is missing', function () {
    Queue::fake();

    report(aggregatedThrowable());

    config()->set('exception-viewer.alarm_enabled', true);
    config()->set('exception-viewer.log_per_time_frame', 1);

    report(aggregatedThrowable());

    Queue::assertNotPushed(SendExceptionAlarm::class);
});

it('does not fail the application flow when queued discord alarming fails', function () {
    config()->set('exception-viewer.discord_webhook_url', 'https://discord.test/webhook');

    Http::fake(fn () => throw new RuntimeException('Discord unavailable'));

    expect(fn () => (new SendExceptionAlarm('LOG_LEVEL: error'))->handle(app(ExceptionAlarmNotifier::class)))
        ->not->toThrow(Throwable::class);
});

it('sends alarms until the configured threshold is reached and then starts delaying them', function () {
    config()->set('exception-viewer.alarm_enabled', true);
    config()->set('exception-viewer.discord_webhook_url', 'https://discord.test/webhook');
    config()->set('exception-viewer.log_per_time_frame', 3);
    config()->set('exception-viewer.delay_between_alarms', 5);

    Queue::fake();

    report(aggregatedThrowable());
    report(aggregatedThrowable());

    Queue::assertPushed(SendExceptionAlarm::class, 2);

    report(aggregatedThrowable());

    Queue::assertPushed(SendExceptionAlarm::class, 3);

    report(aggregatedThrowable());

    Queue::assertPushed(SendExceptionAlarm::class, 3);
});

it('applies the same send-then-delay threshold to queued job exceptions', function () {
    config()->set('queue.default', 'sync');
    config()->set('exception-viewer.alarm_enabled', true);
    config()->set('exception-viewer.discord_webhook_url', 'https://discord.test/webhook');
    config()->set('exception-viewer.log_per_time_frame', 3);
    config()->set('exception-viewer.delay_between_alarms', 5);

    Queue::fake([SendExceptionAlarm::class]);

    foreach (range(1, 2) as $_) {
        try {
            dispatch_sync(new ThrowingQueuedJob('order-123', 'super-secret'));
        } catch (Throwable $throwable) {
            report($throwable);
        }
    }

    Queue::assertPushed(SendExceptionAlarm::class, 2);

    try {
        dispatch_sync(new ThrowingQueuedJob('order-123', 'super-secret'));
    } catch (Throwable $throwable) {
        report($throwable);
    }

    Queue::assertPushed(SendExceptionAlarm::class, 3);

    try {
        dispatch_sync(new ThrowingQueuedJob('order-123', 'super-secret'));
    } catch (Throwable $throwable) {
        report($throwable);
    }

    Queue::assertPushed(SendExceptionAlarm::class, 3);
});

it('prefers queued job context over the active http request for sync jobs', function () {
    config()->set('queue.default', 'sync');

    Route::post('/_exception-viewer/sync-job', function () {
        try {
            dispatch_sync(new ThrowingQueuedJob('order-123', 'super-secret'));
        } catch (Throwable $throwable) {
            report($throwable);
        }

        return response()->json(['ok' => true]);
    });

    $this
        ->postJson('/_exception-viewer/sync-job', [
            'password' => 'request-secret',
        ])
        ->assertOk();

    $row = DB::table('exception_logs')->first();
    $payload = json_decode($row->request_payload, true, flags: JSON_THROW_ON_ERROR);

    expect($row->request_method)->toBe('JOB')
        ->and($row->request_endpoint)->toBe(ThrowingQueuedJob::class)
        ->and($payload['orderId'])->toBe('order-123')
        ->and($payload['password'])->toBe('[MASKED]');
});

it('does not dispatch another discord alarm job until the delay has passed', function () {
    config()->set('exception-viewer.alarm_enabled', true);
    config()->set('exception-viewer.discord_webhook_url', 'https://discord.test/webhook');
    config()->set('exception-viewer.log_per_time_frame', 2);
    config()->set('exception-viewer.delay_between_alarms', 5);

    Queue::fake();

    $firstBurst = Carbon::parse('2026-03-26 10:00:00');
    Carbon::setTestNow($firstBurst);

    report(aggregatedThrowable());
    report(aggregatedThrowable());

    Queue::assertPushed(SendExceptionAlarm::class, 2);

    $secondBurst = $firstBurst->copy()->addMinutes(2);
    Carbon::setTestNow($secondBurst);

    report(aggregatedThrowable());
    report(aggregatedThrowable());

    Queue::assertPushed(SendExceptionAlarm::class, 2);

    $thirdBurst = $firstBurst->copy()->addMinutes(6);
    Carbon::setTestNow($thirdBurst);

    report(aggregatedThrowable());

    Carbon::setTestNow();

    Queue::assertPushed(SendExceptionAlarm::class, 3);
});
