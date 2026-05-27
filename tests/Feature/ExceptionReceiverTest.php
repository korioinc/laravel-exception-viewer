<?php

use Illuminate\Support\Facades\DB;

beforeEach(function () {
    DB::table('exception_logs')->delete();
});

function receiverPayload(array $overrides = []): array
{
    return array_replace_recursive([
        'version' => 1,
        'source' => [
            'key' => 'service-a',
        ],
        'exception' => [
            'key' => str_repeat('a', 64),
            'name' => RuntimeException::class,
            'message' => 'Remote exception.',
            'file' => '/var/www/app/Remote.php',
            'line' => 42,
            'raw_exception' => 'Remote exception.',
            'count' => 7,
            'latest_at' => '2026-03-25T12:30:00+00:00',
        ],
        'request' => [
            'method' => 'POST',
            'endpoint' => '/api/checkout',
            'headers' => '{"authorization":"[MASKED]"}',
            'payload' => '{"order_id":1}',
        ],
        'sent_at' => '2026-03-25T12:31:00+00:00',
    ], $overrides);
}

function enableReceiver(): void
{
    config()->set('exception-viewer.receiver.enabled', true);
    config()->set('exception-viewer.receiver.api_keys', ['secret-key']);
}

it('returns not found when receiver is disabled', function () {
    $this->postJson('/exception-viewer/api/exceptions', receiverPayload(), [
        'Authorization' => 'Bearer secret-key',
    ])->assertNotFound();
});

it('rejects missing or invalid API keys', function (?string $authorization) {
    enableReceiver();

    $headers = $authorization === null ? [] : ['Authorization' => $authorization];

    $this->postJson('/exception-viewer/api/exceptions', receiverPayload(), $headers)
        ->assertUnauthorized();

    expect(DB::table('exception_logs')->count())->toBe(0);
})->with([
    null,
    'Bearer wrong-key',
]);

it('validates payload version and required fields', function () {
    enableReceiver();

    $payload = receiverPayload([
        'version' => 2,
        'source' => [
            'key' => '',
        ],
    ]);

    $this->postJson('/exception-viewer/api/exceptions', $payload, [
        'Authorization' => 'Bearer secret-key',
    ])->assertUnprocessable();

    expect(DB::table('exception_logs')->count())->toBe(0);
});

it('rejects request context fields that are not valid json strings', function () {
    enableReceiver();

    $payload = receiverPayload([
        'request' => [
            'headers' => 'not-json',
            'payload' => '{"order_id":1}',
        ],
    ]);

    $this->postJson('/exception-viewer/api/exceptions', $payload, [
        'Authorization' => 'Bearer secret-key',
    ])->assertUnprocessable();

    expect(DB::table('exception_logs')->count())->toBe(0);
});

it('inserts a valid remote exception with source key identity', function () {
    enableReceiver();

    $this->postJson('/exception-viewer/api/exceptions', receiverPayload(), [
        'Authorization' => 'Bearer secret-key',
    ])
        ->assertAccepted()
        ->assertJsonPath('accepted', true)
        ->assertJsonPath('source_key', 'service-a')
        ->assertJsonPath('count', 7);

    $row = DB::table('exception_logs')->first();

    expect($row->source_key)->toBe('service-a')
        ->and($row->count)->toBe(7)
        ->and($row->request_headers)->toBe('{"authorization":"[MASKED]"}');
});

it('replays snapshots idempotently without incrementing count', function () {
    enableReceiver();
    $payload = receiverPayload();

    $this->postJson('/exception-viewer/api/exceptions', $payload, ['Authorization' => 'Bearer secret-key'])->assertAccepted();
    $this->postJson('/exception-viewer/api/exceptions', $payload, ['Authorization' => 'Bearer secret-key'])->assertAccepted();

    expect(DB::table('exception_logs')->count())->toBe(1)
        ->and(DB::table('exception_logs')->value('count'))->toBe(7);
});

it('converges when another first delivery creates the row during insert', function () {
    enableReceiver();

    DB::unprepared(<<<'SQL'
        CREATE TRIGGER exception_logs_simulate_concurrent_insert
        BEFORE INSERT ON exception_logs
        WHEN NEW.source_key = 'service-race' AND NEW.message <> 'Concurrent winner.'
        BEGIN
            INSERT OR IGNORE INTO exception_logs (
                source_key,
                received_at,
                key,
                name,
                message,
                file,
                line,
                raw_exception,
                request_method,
                request_endpoint,
                request_headers,
                request_payload,
                count,
                latest_at,
                created_at,
                updated_at
            ) VALUES (
                NEW.source_key,
                NEW.received_at,
                NEW.key,
                NEW.name,
                'Concurrent winner.',
                NEW.file,
                NEW.line,
                NEW.raw_exception,
                NEW.request_method,
                NEW.request_endpoint,
                NEW.request_headers,
                NEW.request_payload,
                3,
                '2026-03-25 12:25:00',
                NEW.created_at,
                NEW.updated_at
            );
        END;
    SQL);

    try {
        $this->postJson('/exception-viewer/api/exceptions', receiverPayload([
            'source' => [
                'key' => 'service-race',
            ],
        ]), ['Authorization' => 'Bearer secret-key'])
            ->assertAccepted()
            ->assertJsonPath('source_key', 'service-race')
            ->assertJsonPath('count', 7);
    } finally {
        DB::unprepared('DROP TRIGGER IF EXISTS exception_logs_simulate_concurrent_insert');
    }

    $row = DB::table('exception_logs')->first();

    expect(DB::table('exception_logs')->count())->toBe(1)
        ->and($row->source_key)->toBe('service-race')
        ->and($row->message)->toBe('Remote exception.')
        ->and($row->count)->toBe(7);
});

it('preserves newer details when an older snapshot arrives later', function () {
    enableReceiver();

    $this->postJson('/exception-viewer/api/exceptions', receiverPayload([
        'exception' => [
            'message' => 'Newest detail.',
            'count' => 10,
            'latest_at' => '2026-03-25T12:35:00+00:00',
        ],
    ]), ['Authorization' => 'Bearer secret-key'])->assertAccepted();

    $this->postJson('/exception-viewer/api/exceptions', receiverPayload([
        'exception' => [
            'message' => 'Older detail.',
            'count' => 12,
            'latest_at' => '2026-03-25T12:30:00+00:00',
        ],
    ]), ['Authorization' => 'Bearer secret-key'])->assertAccepted();

    $row = DB::table('exception_logs')->first();

    expect($row->message)->toBe('Newest detail.')
        ->and($row->count)->toBe(12);
});

it('does not regress stored details when an older same-second snapshot arrives later', function () {
    enableReceiver();

    $this->postJson('/exception-viewer/api/exceptions', receiverPayload([
        'exception' => [
            'message' => 'Higher count detail.',
            'count' => 12,
            'latest_at' => '2026-03-25T12:35:00+00:00',
        ],
    ]), ['Authorization' => 'Bearer secret-key'])->assertAccepted();

    $this->postJson('/exception-viewer/api/exceptions', receiverPayload([
        'exception' => [
            'message' => 'Older same-second detail.',
            'count' => 10,
            'latest_at' => '2026-03-25T12:35:00+00:00',
        ],
    ]), ['Authorization' => 'Bearer secret-key'])->assertAccepted();

    $row = DB::table('exception_logs')->first();

    expect($row->message)->toBe('Higher count detail.')
        ->and($row->count)->toBe(12);
});

it('stores the same fingerprint key for different sources', function () {
    enableReceiver();
    $payload = receiverPayload();

    $this->postJson('/exception-viewer/api/exceptions', $payload, ['Authorization' => 'Bearer secret-key'])->assertAccepted();
    $this->postJson('/exception-viewer/api/exceptions', receiverPayload([
        'source' => [
            'key' => 'service-b',
        ],
    ]), ['Authorization' => 'Bearer secret-key'])->assertAccepted();

    expect(DB::table('exception_logs')->count())->toBe(2)
        ->and(DB::table('exception_logs')->pluck('source_key')->all())->toBe(['service-a', 'service-b']);
});
