<?php

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    DB::table('exception_logs')->delete();
});

function insertExceptionLog(array $attributes): int
{
    $timestamp = Carbon::parse($attributes['latest_at'] ?? '2026-03-25 12:00:00');

    return (int) DB::table('exception_logs')->insertGetId([
        'source_key' => array_key_exists('source_key', $attributes) ? $attributes['source_key'] : 'local-app',
        'received_at' => $attributes['received_at'] ?? null,
        'key' => $attributes['key'],
        'name' => $attributes['name'],
        'message' => $attributes['message'],
        'file' => $attributes['file'],
        'line' => $attributes['line'],
        'raw_exception' => $attributes['raw_exception'],
        'request_method' => $attributes['request_method'] ?? null,
        'request_endpoint' => $attributes['request_endpoint'] ?? null,
        'request_headers' => $attributes['request_headers'] ?? null,
        'request_payload' => $attributes['request_payload'] ?? null,
        'count' => $attributes['count'] ?? 1,
        'latest_at' => $timestamp,
        'created_at' => $timestamp,
        'updated_at' => $timestamp,
    ]);
}

it('renders exception rows with expandable details', function () {
    $firstRowId = insertExceptionLog([
        'key' => '8f8f8f8f11111111111111111111111111111111111111111111111111111111',
        'name' => 'RuntimeException',
        'message' => 'Runtime exploded while processing checkout.',
        'file' => '/var/www/app/CheckoutService.php',
        'line' => 184,
        'raw_exception' => "Runtime exploded\n#0 CheckoutService.php:184",
        'request_method' => 'POST',
        'request_endpoint' => '/api/checkout',
        'request_headers' => json_encode(['authorization' => '[MASKED]'], JSON_THROW_ON_ERROR),
        'request_payload' => json_encode(['order_id' => 1], JSON_THROW_ON_ERROR),
        'count' => 7,
        'latest_at' => '2026-03-25 12:30:00',
    ]);

    $secondRowId = insertExceptionLog([
        'key' => '9a9a9a9a22222222222222222222222222222222222222222222222222222222',
        'name' => 'InvalidArgumentException',
        'message' => 'Payload shape is invalid.',
        'file' => '/var/www/app/PayloadValidator.php',
        'line' => 88,
        'raw_exception' => "Payload shape is invalid\n#0 PayloadValidator.php:88",
        'request_method' => 'PUT',
        'request_endpoint' => '/api/orders/1',
        'request_headers' => json_encode(['x-trace-id' => 'trace-1'], JSON_THROW_ON_ERROR),
        'request_payload' => json_encode(['status' => 'paid'], JSON_THROW_ON_ERROR),
        'count' => 2,
        'latest_at' => '2026-03-25 11:30:00',
    ]);

    $response = $this->get('/exception-viewer');
    $response->assertOk()
        ->assertViewIs('exception-viewer::pages.index')
        ->assertSee('Exception Viewer')
        ->assertSee(strtoupper(app()->environment()))
        ->assertSee('RuntimeException')
        ->assertSee('InvalidArgumentException')
        ->assertSee('8f8f8f8f')
        ->assertSee('Copy')
        ->assertSee('Link')
        ->assertSee('Delete')
        ->assertSee('aria-label="Copy source export link"', false)
        ->assertSee('aria-label="Copy exception markdown"', false)
        ->assertSee('aria-label="Copy exception detail link"', false)
        ->assertSee('aria-label="Delete exception row"', false)
        ->assertSee('data-row-delete-button', false)
        ->assertSee('data-confirm-label="Confirm delete exception row"', false)
        ->assertSee('action="'.route('exception-viewer.delete', ['id' => $firstRowId]).'"', false)
        ->assertSee('action="'.route('exception-viewer.delete', ['id' => $secondRowId]).'"', false)
        ->assertSee('name="redirect_to" value="/exception-viewer"', false)
        ->assertSee('aria-label="Delete current source exception logs"', false)
        ->assertSee('aria-label="Delete all exception logs"', false)
        ->assertSee('name="source" value="local-app"', false)
        ->assertSee('onsubmit="return confirmAllSourcePurge(this);"', false)
        ->assertSee('data-confirmation-token="all"', false)
        ->assertSee('data-confirmation-field="all_source_confirmation"', false)
        ->assertSee('name="all_source_confirmation" value=""', false)
        ->assertSee('🚛')
        ->assertDontSee('Clear all')
        ->assertSee('rel="icon"', false)
        ->assertSee('Location')
        ->assertSee('/var/www/app/CheckoutService.php:184')
        ->assertSee('POST')
        ->assertSee('/api/checkout')
        ->assertSee('authorization')
        ->assertSee('order_id')
        ->assertSee('Runtime exploded');
});

it('defaults to the local source and switches sources through header tabs', function () {
    insertExceptionLog([
        'source_key' => 'local-app',
        'key' => '33333333cccccccccccccccccccccccccccccccccccccccccccccccccccc',
        'name' => 'LogicException',
        'message' => 'Local app failed.',
        'file' => '/var/www/app/LocalApp.php',
        'line' => 5,
        'raw_exception' => 'Local app failed',
        'latest_at' => '2026-03-25 12:40:00',
    ]);

    insertExceptionLog([
        'source_key' => 'service-a',
        'key' => '11111111aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
        'name' => 'RuntimeException',
        'message' => 'Service A failed.',
        'file' => '/var/www/app/ServiceA.php',
        'line' => 10,
        'raw_exception' => 'Service A failed',
        'latest_at' => '2026-03-25 12:30:00',
    ]);

    insertExceptionLog([
        'source_key' => 'service-b',
        'key' => '22222222bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb',
        'name' => 'RuntimeException',
        'message' => 'Service B failed.',
        'file' => '/var/www/app/ServiceB.php',
        'line' => 20,
        'raw_exception' => 'Service B failed',
        'latest_at' => '2026-03-25 12:20:00',
    ]);

    $response = $this->get('/exception-viewer');

    $response->assertOk()
        ->assertSee('Local App')
        ->assertSee('SERVICE-A')
        ->assertSee('SERVICE-B')
        ->assertDontSee('>Service A<', false)
        ->assertDontSee('>Service B<', false)
        ->assertSee('href="'.route('exception-viewer.index').'"', false)
        ->assertDontSee('source=local-app', false)
        ->assertDontSee('All sources')
        ->assertDontSee('Groups')
        ->assertSee('/var/www/app/LocalApp.php:5')
        ->assertDontSee('/var/www/app/ServiceA.php:10')
        ->assertDontSee('/var/www/app/ServiceB.php:20');

    $response = $this->get('/exception-viewer?source=service-a');

    $response->assertOk()
        ->assertSee('/var/www/app/ServiceA.php:10')
        ->assertDontSee('/var/www/app/ServiceB.php:20');
});

it('renders the configured local source label without changing local source keys', function () {
    config()->set('exception-viewer.source.label', 'API Server');

    insertExceptionLog([
        'source_key' => 'local-app',
        'key' => '33333333cccccccccccccccccccccccccccccccccccccccccccccccccccc',
        'name' => 'LogicException',
        'message' => 'Primary runtime failed.',
        'file' => '/var/www/app/PrimaryRuntime.php',
        'line' => 5,
        'raw_exception' => 'Primary runtime failed',
        'latest_at' => '2026-03-25 12:40:00',
    ]);

    insertExceptionLog([
        'source_key' => 'service-a',
        'key' => '11111111aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
        'name' => 'RuntimeException',
        'message' => 'Service A failed.',
        'file' => '/var/www/app/ServiceA.php',
        'line' => 10,
        'raw_exception' => 'Service A failed',
        'latest_at' => '2026-03-25 12:30:00',
    ]);

    $response = $this->get('/exception-viewer');

    $response->assertOk()
        ->assertSee('>API Server<', false)
        ->assertSee('SERVICE-A')
        ->assertSee('title="Delete API Server exception logs"', false)
        ->assertSee('name="source" value="local-app"', false)
        ->assertSee('href="'.route('exception-viewer.index').'"', false)
        ->assertDontSee('source=local-app', false);

    DB::table('exception_logs')->delete();

    $this->get('/exception-viewer')
        ->assertOk()
        ->assertSee('API Server has no recorded exceptions yet.');
});

it('falls back to the default local source label when the configured label is blank', function () {
    config()->set('exception-viewer.source.label', '   ');

    insertExceptionLog([
        'source_key' => 'local-app',
        'key' => '33333333cccccccccccccccccccccccccccccccccccccccccccccccccccc',
        'name' => 'LogicException',
        'message' => 'Primary runtime failed.',
        'file' => '/var/www/app/PrimaryRuntime.php',
        'line' => 5,
        'raw_exception' => 'Primary runtime failed',
        'latest_at' => '2026-03-25 12:40:00',
    ]);

    $this->get('/exception-viewer')
        ->assertOk()
        ->assertSee('Local App')
        ->assertSee('title="Delete Local App exception logs"', false)
        ->assertSee('name="source" value="local-app"', false);
});

it('returns grouped exception summaries as JSON for automation clients', function () {
    insertExceptionLog([
        'source_key' => 'service-b',
        'key' => '11111111bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb',
        'name' => 'RuntimeException',
        'message' => 'Service B runtime detail must stay private.',
        'file' => '/var/www/service-b/Runtime.php',
        'line' => 10,
        'raw_exception' => 'Service B stack trace',
        'request_headers' => '{"authorization":"[MASKED]"}',
        'request_payload' => '{"order_id":1}',
        'count' => 4,
        'latest_at' => '2026-03-25 12:20:00',
    ]);

    insertExceptionLog([
        'source_key' => 'service-a',
        'key' => '22222222aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
        'name' => 'RuntimeException',
        'message' => 'Older service A runtime detail.',
        'file' => '/var/www/service-a/RuntimeOld.php',
        'line' => 20,
        'raw_exception' => 'Older service A stack trace',
        'count' => 2,
        'latest_at' => '2026-03-25 11:30:00',
    ]);

    insertExceptionLog([
        'source_key' => 'service-a',
        'key' => '33333333aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
        'name' => 'RuntimeException',
        'message' => 'Newer service A runtime detail.',
        'file' => '/var/www/service-a/RuntimeNew.php',
        'line' => 30,
        'raw_exception' => 'Newer service A stack trace',
        'count' => 5,
        'latest_at' => '2026-03-25 12:30:00',
    ]);

    insertExceptionLog([
        'source_key' => 'service-a',
        'key' => '44444444aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
        'name' => 'InvalidArgumentException',
        'message' => 'Service A invalid argument detail.',
        'file' => '/var/www/service-a/Invalid.php',
        'line' => 40,
        'raw_exception' => 'Service A invalid argument stack trace',
        'count' => 7,
        'latest_at' => '2026-03-25 12:30:00',
    ]);

    insertExceptionLog([
        'source_key' => 'local-app',
        'key' => '55555555cccccccccccccccccccccccccccccccccccccccccccccccccccc',
        'name' => 'LogicException',
        'message' => 'Local app detail.',
        'file' => '/var/www/local/Logic.php',
        'line' => 50,
        'raw_exception' => 'Local app stack trace',
        'count' => 1,
        'latest_at' => '2026-03-25 12:10:00',
    ]);

    insertExceptionLog([
        'source_key' => null,
        'key' => '66666666cccccccccccccccccccccccccccccccccccccccccccccccccccc',
        'name' => 'RuntimeException',
        'message' => 'Legacy null source detail.',
        'file' => '/var/www/local/Runtime.php',
        'line' => 60,
        'raw_exception' => 'Legacy null source stack trace',
        'count' => 3,
        'latest_at' => '2026-03-25 12:40:00',
    ]);

    insertExceptionLog([
        'source_key' => '',
        'key' => '77777777cccccccccccccccccccccccccccccccccccccccccccccccccccc',
        'name' => 'RuntimeException',
        'message' => 'Legacy empty source detail.',
        'file' => '/var/www/local/RuntimeEmpty.php',
        'line' => 70,
        'raw_exception' => 'Legacy empty source stack trace',
        'count' => 2,
        'latest_at' => '2026-03-25 12:35:00',
    ]);

    $response = $this->getJson('/exception-viewer/json');

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/json')
        ->assertExactJson([
            [
                'name' => 'local-app',
                'exceptions' => [
                    [
                        'name' => 'RuntimeException',
                        'count' => 5,
                        'latest_at' => '2026-03-25 12:40:00',
                    ],
                    [
                        'name' => 'LogicException',
                        'count' => 1,
                        'latest_at' => '2026-03-25 12:10:00',
                    ],
                ],
                'total_count' => 2,
                'total_error_count' => 6,
            ],
            [
                'name' => 'service-a',
                'exceptions' => [
                    [
                        'name' => 'InvalidArgumentException',
                        'count' => 7,
                        'latest_at' => '2026-03-25 12:30:00',
                    ],
                    [
                        'name' => 'RuntimeException',
                        'count' => 7,
                        'latest_at' => '2026-03-25 12:30:00',
                    ],
                ],
                'total_count' => 2,
                'total_error_count' => 14,
            ],
            [
                'name' => 'service-b',
                'exceptions' => [
                    [
                        'name' => 'RuntimeException',
                        'count' => 4,
                        'latest_at' => '2026-03-25 12:20:00',
                    ],
                ],
                'total_count' => 1,
                'total_error_count' => 4,
            ],
        ]);

    expect($response->getContent())->not->toContain(
        'key',
        'message',
        'file',
        'line',
        'request_headers',
        'request_payload',
        'raw_exception',
        'detail_url',
        'markdown',
        '/var/www',
        'stack trace',
    );
});

it('returns an empty JSON summary when no exception logs exist', function () {
    $this->get('/exception-viewer/json')
        ->assertOk()
        ->assertExactJson([]);
});

it('blocks summary JSON access in production by default', function () {
    $this->app['env'] = 'production';

    $this->getJson('/exception-viewer/json')
        ->assertNotFound();
});

it('returns local exception details by default when sources share a fingerprint', function () {
    $key = 'abababab11111111111111111111111111111111111111111111111111111111';

    insertExceptionLog([
        'source_key' => 'local-app',
        'key' => $key,
        'name' => RuntimeException::class,
        'message' => 'Local exception detail.',
        'file' => '/var/www/app/LocalApp.php',
        'line' => 10,
        'raw_exception' => 'Local exception detail.',
        'latest_at' => '2026-03-25 12:30:00',
    ]);

    insertExceptionLog([
        'source_key' => 'service-a',
        'key' => $key,
        'name' => RuntimeException::class,
        'message' => 'Remote exception detail.',
        'file' => '/var/www/app/ServiceA.php',
        'line' => 20,
        'raw_exception' => 'Remote exception detail.',
        'latest_at' => '2026-03-25 12:40:00',
    ]);

    $this->get('/exception-viewer/'.$key)
        ->assertOk()
        ->assertSee('Local exception detail.')
        ->assertDontSee('Remote exception detail.');
});

it('blocks viewer access in production by default', function () {
    $this->app['env'] = 'production';

    $response = $this->get('/exception-viewer');

    $response->assertNotFound();
});

it('sorts the list by count when requested', function () {
    insertExceptionLog([
        'key' => 'eeeeeeee11111111111111111111111111111111111111111111111111111111',
        'name' => 'RuntimeException',
        'message' => 'Lower count row.',
        'file' => '/var/www/app/CheckoutService.php',
        'line' => 184,
        'raw_exception' => 'Lower count row',
        'count' => 2,
        'latest_at' => '2026-03-25 12:30:00',
    ]);

    insertExceptionLog([
        'key' => 'ffffffff22222222222222222222222222222222222222222222222222222222',
        'name' => 'RuntimeException',
        'message' => 'Higher count row.',
        'file' => '/var/www/app/CheckoutService.php',
        'line' => 188,
        'raw_exception' => 'Higher count row',
        'count' => 9,
        'latest_at' => '2026-03-25 11:30:00',
    ]);

    $response = $this->get('/exception-viewer?sort=count');

    $response->assertOk()
        ->assertSeeInOrder([
            'ffffffff',
            'eeeeeeee',
        ]);
});

it('returns the exception details as markdown', function () {
    $key = '1212121211111111111111111111111111111111111111111111111111111111';

    insertExceptionLog([
        'key' => $key,
        'name' => 'RuntimeException',
        'message' => 'Checkout worker stalled.',
        'file' => '/var/www/app/CheckoutService.php',
        'line' => 184,
        'raw_exception' => "Runtime exploded\n#0 CheckoutService.php:184",
        'request_method' => 'POST',
        'request_endpoint' => '/api/checkout',
        'request_headers' => '{"authorization":"[MASKED]"}',
        'request_payload' => '{"order_id":1}',
        'count' => 7,
        'latest_at' => '2026-03-25 12:30:00',
    ]);

    $response = $this->get('/exception-viewer/'.$key);

    $response->assertOk()
        ->assertHeader('Content-Type', 'text/markdown; charset=UTF-8')
        ->assertSee('# Exception')
        ->assertSee('## Request')
        ->assertSee('## Headers')
        ->assertSee('## Payload')
        ->assertSee('## Context')
        ->assertSee('{"authorization":"[MASKED]"}', false)
        ->assertSee('{"order_id":1}', false)
        ->assertSee('/api/checkout')
        ->assertSee('Runtime exploded')
        ->assertDontSee('- Key:')
        ->assertDontSee('- Count:')
        ->assertDontSee('- Latest At:');
});

it('returns markdown that remains well-formed when exception content contains backtick fences', function () {
    $key = '3434343411111111111111111111111111111111111111111111111111111111';

    insertExceptionLog([
        'key' => $key,
        'name' => 'RuntimeException',
        'message' => 'Backtick fence collision.',
        'file' => '/var/www/app/MarkdownService.php',
        'line' => 99,
        'raw_exception' => "Failure before block\n```text\ninner block\n```\nFailure after block",
        'request_method' => 'POST',
        'request_endpoint' => '/api/markdown',
        'request_headers' => '{"snippet":"```json\n{\"bad\":true}\n```"}',
        'request_payload' => '{"message":"payload is safe"}',
        'count' => 1,
        'latest_at' => '2026-03-25 12:30:00',
    ]);

    $response = $this->get('/exception-viewer/'.$key);

    $response->assertOk();

    expect($response->getContent())->toBe(implode("\n", [
        '# Exception',
        '',
        '- Source: `local-app`',
        '- Name: `RuntimeException`',
        '- Message: Backtick fence collision.',
        '- File: `/var/www/app/MarkdownService.php`',
        '- Line: 99',
        '',
        '## Request',
        '',
        '- Method: POST',
        '- Endpoint: /api/markdown',
        '',
        '## Headers',
        '',
        '~~~json',
        '{"snippet":"```json\n{\"bad\":true}\n```"}',
        '~~~',
        '',
        '## Payload',
        '',
        '~~~json',
        '{"message":"payload is safe"}',
        '~~~',
        '',
        '## Context',
        '',
        '~~~text',
        "Failure before block\n```text\ninner block\n```\nFailure after block",
        '~~~',
    ]));
});

it('returns all exceptions as markdown sections', function () {
    insertExceptionLog([
        'key' => 'abababab11111111111111111111111111111111111111111111111111111111',
        'name' => 'RuntimeException',
        'message' => 'Newest exception.',
        'file' => '/var/www/app/NewestService.php',
        'line' => 11,
        'raw_exception' => 'Newest context',
        'request_method' => 'POST',
        'request_endpoint' => '/api/newest',
        'request_headers' => '{"authorization":"[MASKED]"}',
        'request_payload' => '{"value":"new"}',
        'count' => 3,
        'latest_at' => '2026-03-25 12:30:00',
    ]);

    insertExceptionLog([
        'key' => 'cdcdcdcd22222222222222222222222222222222222222222222222222222222',
        'name' => 'InvalidArgumentException',
        'message' => 'Older exception.',
        'file' => '/var/www/app/OlderService.php',
        'line' => 22,
        'raw_exception' => 'Older context',
        'request_method' => 'GET',
        'request_endpoint' => '/api/older',
        'request_headers' => '{"x-trace-id":"abc"}',
        'request_payload' => '{"value":"old"}',
        'count' => 1,
        'latest_at' => '2026-03-25 11:30:00',
    ]);

    $response = $this->get('/exception-viewer/all');

    $response->assertOk()
        ->assertHeader('Content-Type', 'text/markdown; charset=UTF-8')
        ->assertSeeInOrder([
            'Newest exception.',
            '---',
            'Older exception.',
        ])
        ->assertSee('# Exception')
        ->assertSee('/api/newest')
        ->assertSee('/api/older')
        ->assertSee('Newest context')
        ->assertSee('Older context')
        ->assertDontSee('- Key:')
        ->assertDontSee('- Count:')
        ->assertDontSee('- Latest At:');
});

it('returns source-filtered markdown sections', function () {
    insertExceptionLog([
        'source_key' => 'local-app',
        'key' => str_repeat('1', 64),
        'name' => 'RuntimeException',
        'message' => 'Local app exception.',
        'file' => '/var/www/local/App.php',
        'line' => 10,
        'raw_exception' => 'Local app context',
        'latest_at' => '2026-03-25 12:40:00',
    ]);

    insertExceptionLog([
        'source_key' => 'service-a',
        'key' => str_repeat('a', 64),
        'name' => 'RuntimeException',
        'message' => 'Service A exception.',
        'file' => '/var/www/service-a/App.php',
        'line' => 11,
        'raw_exception' => 'Service A context',
        'latest_at' => '2026-03-25 12:30:00',
    ]);

    insertExceptionLog([
        'source_key' => 'service-b',
        'key' => str_repeat('b', 64),
        'name' => 'RuntimeException',
        'message' => 'Service B exception.',
        'file' => '/var/www/service-b/App.php',
        'line' => 22,
        'raw_exception' => 'Service B context',
        'latest_at' => '2026-03-25 12:20:00',
    ]);

    $this->get('/exception-viewer/all')
        ->assertOk()
        ->assertSee('Local app exception.')
        ->assertDontSee('Service A exception.')
        ->assertDontSee('Service B exception.');

    $this->get('/exception-viewer/all?source=service-a')
        ->assertOk()
        ->assertSee('Service A exception.')
        ->assertDontSee('Service B exception.');
});

it('includes source key in markdown exports', function () {
    $key = str_repeat('c', 64);

    insertExceptionLog([
        'source_key' => 'service-a',
        'received_at' => '2026-03-25 12:31:00',
        'key' => $key,
        'name' => 'RuntimeException',
        'message' => 'Central row.',
        'file' => '/var/www/app/Central.php',
        'line' => 77,
        'raw_exception' => 'Central row',
        'latest_at' => '2026-03-25 12:30:00',
    ]);

    $this->get('/exception-viewer/'.$key.'?source=service-a')
        ->assertOk()
        ->assertSee('- Source: `service-a`');
});

it('omits request sections in the detail markdown when request context is missing', function () {
    $key = '5656565611111111111111111111111111111111111111111111111111111111';

    insertExceptionLog([
        'key' => $key,
        'name' => 'RuntimeException',
        'message' => 'Console-only exception.',
        'file' => '/var/www/app/ConsoleCommand.php',
        'line' => 44,
        'raw_exception' => 'Console detail context',
        'request_method' => null,
        'request_endpoint' => null,
        'request_headers' => null,
        'request_payload' => null,
        'count' => 1,
        'latest_at' => '2026-03-25 12:30:00',
    ]);

    $response = $this->get('/exception-viewer/'.$key);

    $response->assertOk()
        ->assertSee('Console detail context')
        ->assertDontSee('## Request')
        ->assertDontSee('## Headers')
        ->assertDontSee('## Payload')
        ->assertDontSee('No HTTP request');
});

it('deletes only the requested exception row by id', function () {
    $sharedKey = 'edededed11111111111111111111111111111111111111111111111111111111';

    $deletedId = insertExceptionLog([
        'source_key' => 'service-a',
        'key' => $sharedKey,
        'name' => 'RuntimeException',
        'message' => 'Delete service A row.',
        'file' => '/var/www/app/ServiceADeleteService.php',
        'line' => 20,
        'raw_exception' => 'Delete service A row',
        'latest_at' => '2026-03-25 11:30:00',
    ]);

    $keptSameKeyId = insertExceptionLog([
        'source_key' => 'service-b',
        'key' => $sharedKey,
        'name' => 'RuntimeException',
        'message' => 'Keep service B row.',
        'file' => '/var/www/app/ServiceBDeleteService.php',
        'line' => 30,
        'raw_exception' => 'Keep service B row',
        'latest_at' => '2026-03-25 11:00:00',
    ]);

    $keptOtherId = insertExceptionLog([
        'source_key' => 'local-app',
        'key' => 'fefefefe22222222222222222222222222222222222222222222222222222222',
        'name' => 'LogicException',
        'message' => 'Keep local row.',
        'file' => '/var/www/app/LocalKeepService.php',
        'line' => 10,
        'raw_exception' => 'Keep local row',
        'latest_at' => '2026-03-25 12:30:00',
    ]);

    $response = $this
        ->withSession(['_token' => 'test-token'])
        ->post('/exception-viewer/entries/'.$deletedId.'/delete', [
            '_token' => 'test-token',
            'redirect_to' => '/exception-viewer?source=service-a&sort=count',
        ]);

    $response->assertRedirect('/exception-viewer?source=service-a&sort=count');
    expect(DB::table('exception_logs')->pluck('id')->all())->toContain($keptSameKeyId, $keptOtherId)
        ->not->toContain($deletedId);
});

it('redirects without deleting exception rows when the requested row id is stale', function () {
    $keptId = insertExceptionLog([
        'source_key' => 'local-app',
        'key' => 'acacacac11111111111111111111111111111111111111111111111111111111',
        'name' => 'RuntimeException',
        'message' => 'Keep local row after stale delete.',
        'file' => '/var/www/app/StaleDeleteService.php',
        'line' => 10,
        'raw_exception' => 'Keep local row after stale delete',
        'latest_at' => '2026-03-25 12:30:00',
    ]);

    $response = $this
        ->withSession(['_token' => 'test-token'])
        ->post('/exception-viewer/entries/999999/delete', [
            '_token' => 'test-token',
            'redirect_to' => '/exception-viewer',
        ]);

    $response->assertRedirect('/exception-viewer');
    expect(DB::table('exception_logs')->pluck('id')->all())->toBe([$keptId]);
});

it('falls back to the viewer index for unsafe row delete redirects', function (string $redirectTo) {
    $deletedId = insertExceptionLog([
        'source_key' => 'local-app',
        'key' => hash('sha256', 'unsafe-row-delete-'.$redirectTo),
        'name' => 'RuntimeException',
        'message' => 'Delete local row with unsafe redirect.',
        'file' => '/var/www/app/UnsafeRedirectDeleteService.php',
        'line' => 10,
        'raw_exception' => 'Delete local row with unsafe redirect',
        'latest_at' => '2026-03-25 12:30:00',
    ]);

    $response = $this
        ->withSession(['_token' => 'test-token'])
        ->post('/exception-viewer/entries/'.$deletedId.'/delete', [
            '_token' => 'test-token',
            'redirect_to' => $redirectTo,
        ]);

    $response->assertRedirect(route('exception-viewer.index'));
    expect(DB::table('exception_logs')->where('id', $deletedId)->exists())->toBeFalse();
})->with([
    'external URL' => ['https://example.com/exception-viewer'],
    'protocol-relative URL' => ['//example.com/exception-viewer'],
]);

it('blocks row delete in production by default', function () {
    $rowId = insertExceptionLog([
        'source_key' => 'local-app',
        'key' => 'bcbcbcbc11111111111111111111111111111111111111111111111111111111',
        'name' => 'RuntimeException',
        'message' => 'Keep local row in production.',
        'file' => '/var/www/app/ProductionDeleteService.php',
        'line' => 10,
        'raw_exception' => 'Keep local row in production',
        'latest_at' => '2026-03-25 12:30:00',
    ]);

    $this->app['env'] = 'production';

    $this
        ->withSession(['_token' => 'test-token'])
        ->post('/exception-viewer/entries/'.$rowId.'/delete', [
            '_token' => 'test-token',
            'redirect_to' => '/exception-viewer',
        ])
        ->assertNotFound();

    expect(DB::table('exception_logs')->where('id', $rowId)->exists())->toBeTrue();
});

it('purges exception logs for the selected source from the viewer action', function () {
    insertExceptionLog([
        'source_key' => 'local-app',
        'key' => 'edededed11111111111111111111111111111111111111111111111111111111',
        'name' => 'RuntimeException',
        'message' => 'Keep local.',
        'file' => '/var/www/app/LocalDeleteService.php',
        'line' => 10,
        'raw_exception' => 'Keep local',
        'latest_at' => '2026-03-25 12:30:00',
    ]);

    insertExceptionLog([
        'source_key' => 'service-a',
        'key' => 'fefefefe22222222222222222222222222222222222222222222222222222222',
        'name' => 'InvalidArgumentException',
        'message' => 'Delete service A.',
        'file' => '/var/www/app/ServiceADeleteService.php',
        'line' => 20,
        'raw_exception' => 'Delete service A',
        'latest_at' => '2026-03-25 11:30:00',
    ]);

    insertExceptionLog([
        'source_key' => 'service-b',
        'key' => 'abababab33333333333333333333333333333333333333333333333333333333',
        'name' => 'LogicException',
        'message' => 'Keep service B.',
        'file' => '/var/www/app/ServiceBDeleteService.php',
        'line' => 30,
        'raw_exception' => 'Keep service B',
        'latest_at' => '2026-03-25 11:00:00',
    ]);

    $response = $this
        ->withSession(['_token' => 'test-token'])
        ->post('/exception-viewer/purge', [
            '_token' => 'test-token',
            'source' => 'service-a',
            'redirect_to' => '/exception-viewer?source=service-a',
        ]);

    $response->assertRedirect('/exception-viewer?source=service-a');
    expect(DB::table('exception_logs')->orderBy('source_key')->pluck('source_key')->all())->toBe([
        'local-app',
        'service-b',
    ]);
});

it('purges all exception logs from the separate all-sources action', function () {
    insertExceptionLog([
        'source_key' => 'local-app',
        'key' => 'acacacac11111111111111111111111111111111111111111111111111111111',
        'name' => 'RuntimeException',
        'message' => 'Delete local.',
        'file' => '/var/www/app/LocalDeleteService.php',
        'line' => 10,
        'raw_exception' => 'Delete local',
        'latest_at' => '2026-03-25 12:30:00',
    ]);

    insertExceptionLog([
        'source_key' => 'service-a',
        'key' => 'bcbcbcbc22222222222222222222222222222222222222222222222222222222',
        'name' => 'InvalidArgumentException',
        'message' => 'Delete service A.',
        'file' => '/var/www/app/ServiceADeleteService.php',
        'line' => 20,
        'raw_exception' => 'Delete service A',
        'latest_at' => '2026-03-25 11:30:00',
    ]);

    insertExceptionLog([
        'source_key' => null,
        'key' => 'cdcdcdcd33333333333333333333333333333333333333333333333333333333',
        'name' => 'LogicException',
        'message' => 'Delete null source.',
        'file' => '/var/www/app/NullSourceDeleteService.php',
        'line' => 30,
        'raw_exception' => 'Delete null source',
        'latest_at' => '2026-03-25 11:00:00',
    ]);

    insertExceptionLog([
        'source_key' => '',
        'key' => 'dcdcdcdc44444444444444444444444444444444444444444444444444444444',
        'name' => 'DomainException',
        'message' => 'Delete empty source.',
        'file' => '/var/www/app/EmptySourceDeleteService.php',
        'line' => 40,
        'raw_exception' => 'Delete empty source',
        'latest_at' => '2026-03-25 10:30:00',
    ]);

    $response = $this
        ->withSession(['_token' => 'test-token'])
        ->post('/exception-viewer/purge', [
            '_token' => 'test-token',
            'scope' => 'all',
            'all_source_confirmation' => 'all',
            'redirect_to' => '/exception-viewer',
        ]);

    $response->assertRedirect('/exception-viewer');
    expect(DB::table('exception_logs')->count())->toBe(0);
});

it('accepts surrounding whitespace around the all-source confirmation token', function () {
    insertExceptionLog([
        'source_key' => 'local-app',
        'key' => 'eeeeeeee11111111111111111111111111111111111111111111111111111111',
        'name' => 'RuntimeException',
        'message' => 'Delete local with padded token.',
        'file' => '/var/www/app/LocalPaddedTokenDeleteService.php',
        'line' => 10,
        'raw_exception' => 'Delete local with padded token',
        'latest_at' => '2026-03-25 12:30:00',
    ]);

    insertExceptionLog([
        'source_key' => 'service-a',
        'key' => 'efefefef22222222222222222222222222222222222222222222222222222222',
        'name' => 'InvalidArgumentException',
        'message' => 'Delete service A with padded token.',
        'file' => '/var/www/app/ServiceAPaddedTokenDeleteService.php',
        'line' => 20,
        'raw_exception' => 'Delete service A with padded token',
        'latest_at' => '2026-03-25 11:30:00',
    ]);

    $response = $this
        ->withSession(['_token' => 'test-token'])
        ->post('/exception-viewer/purge', [
            '_token' => 'test-token',
            'scope' => 'all',
            'all_source_confirmation' => ' all ',
            'redirect_to' => '/exception-viewer',
        ]);

    $response->assertRedirect('/exception-viewer');
    expect(DB::table('exception_logs')->count())->toBe(0);
});

it('does not purge all exception logs without the all-source confirmation token', function () {
    insertExceptionLog([
        'source_key' => 'local-app',
        'key' => 'eaeaeaea11111111111111111111111111111111111111111111111111111111',
        'name' => 'RuntimeException',
        'message' => 'Keep local without token.',
        'file' => '/var/www/app/LocalKeepService.php',
        'line' => 10,
        'raw_exception' => 'Keep local without token',
        'latest_at' => '2026-03-25 12:30:00',
    ]);

    insertExceptionLog([
        'source_key' => 'service-a',
        'key' => 'ebebebeb22222222222222222222222222222222222222222222222222222222',
        'name' => 'InvalidArgumentException',
        'message' => 'Keep service A without token.',
        'file' => '/var/www/app/ServiceAKeepService.php',
        'line' => 20,
        'raw_exception' => 'Keep service A without token',
        'latest_at' => '2026-03-25 11:30:00',
    ]);

    $response = $this
        ->withSession(['_token' => 'test-token'])
        ->post('/exception-viewer/purge', [
            '_token' => 'test-token',
            'scope' => 'all',
            'redirect_to' => '/exception-viewer',
        ]);

    $response->assertRedirect('/exception-viewer');
    expect(DB::table('exception_logs')->count())->toBe(2);
});

it('does not purge all exception logs with invalid all-source confirmation tokens', function (string $confirmation) {
    insertExceptionLog([
        'source_key' => 'local-app',
        'key' => hash('sha256', 'local-app-'.$confirmation),
        'name' => 'RuntimeException',
        'message' => 'Keep local with invalid token.',
        'file' => '/var/www/app/LocalInvalidTokenKeepService.php',
        'line' => 10,
        'raw_exception' => 'Keep local with invalid token',
        'latest_at' => '2026-03-25 12:30:00',
    ]);

    insertExceptionLog([
        'source_key' => 'service-a',
        'key' => hash('sha256', 'service-a-'.$confirmation),
        'name' => 'InvalidArgumentException',
        'message' => 'Keep service A with invalid token.',
        'file' => '/var/www/app/ServiceAInvalidTokenKeepService.php',
        'line' => 20,
        'raw_exception' => 'Keep service A with invalid token',
        'latest_at' => '2026-03-25 11:30:00',
    ]);

    $response = $this
        ->withSession(['_token' => 'test-token'])
        ->post('/exception-viewer/purge', [
            '_token' => 'test-token',
            'scope' => 'all',
            'all_source_confirmation' => $confirmation,
            'redirect_to' => '/exception-viewer',
        ]);

    $response->assertRedirect('/exception-viewer');
    expect(DB::table('exception_logs')->count())->toBe(2);
})->with([
    'empty token' => [''],
    'wrong token' => ['delete'],
    'wrong-case token' => ['ALL'],
]);
