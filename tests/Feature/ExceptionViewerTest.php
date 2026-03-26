<?php

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    DB::table('exception_logs')->delete();
});

function insertExceptionLog(array $attributes): void
{
    $timestamp = Carbon::parse($attributes['latest_at'] ?? '2026-03-25 12:00:00');

    DB::table('exception_logs')->insert([
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

it('renders grouped exception rows with expandable details', function () {
    insertExceptionLog([
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

    insertExceptionLog([
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
        ->assertSee('All')
        ->assertSee('Exception Viewer')
        ->assertSee(strtoupper(app()->environment()))
        ->assertSee('RuntimeException')
        ->assertSee('InvalidArgumentException')
        ->assertSee('8f8f8f8f')
        ->assertSee('Copy')
        ->assertSee('Link')
        ->assertSee('aria-label="Copy all exception export link"', false)
        ->assertSee('aria-label="Copy exception markdown"', false)
        ->assertSee('aria-label="Copy exception detail link"', false)
        ->assertSee('rel="icon"', false)
        ->assertSee('Location')
        ->assertSee('/var/www/app/CheckoutService.php:184')
        ->assertSee('POST')
        ->assertSee('/api/checkout')
        ->assertSee('authorization')
        ->assertSee('order_id')
        ->assertSee('Runtime exploded');
});

it('blocks viewer access in production by default', function () {
    $this->app['env'] = 'production';

    $response = $this->get('/exception-viewer');

    $response->assertNotFound();
});

it('filters the list by selected exception group', function () {
    insertExceptionLog([
        'key' => 'aaaaaaaa11111111111111111111111111111111111111111111111111111111',
        'name' => 'RuntimeException',
        'message' => 'Runtime exploded while processing checkout.',
        'file' => '/var/www/app/CheckoutService.php',
        'line' => 184,
        'raw_exception' => 'Runtime exploded',
        'latest_at' => '2026-03-25 12:30:00',
    ]);

    insertExceptionLog([
        'key' => 'bbbbbbbb22222222222222222222222222222222222222222222222222222222',
        'name' => 'InvalidArgumentException',
        'message' => 'Payload shape is invalid.',
        'file' => '/var/www/app/PayloadValidator.php',
        'line' => 88,
        'raw_exception' => 'Payload shape is invalid',
        'latest_at' => '2026-03-25 11:30:00',
    ]);

    $response = $this->get('/exception-viewer?group=RuntimeException');

    $response->assertOk()
        ->assertSee('/var/www/app/CheckoutService.php:184')
        ->assertDontSee('/var/www/app/PayloadValidator.php:88');
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

it('purges all exception logs from the viewer action', function () {
    insertExceptionLog([
        'key' => 'edededed11111111111111111111111111111111111111111111111111111111',
        'name' => 'RuntimeException',
        'message' => 'Delete me.',
        'file' => '/var/www/app/DeleteService.php',
        'line' => 10,
        'raw_exception' => 'Delete me',
        'latest_at' => '2026-03-25 12:30:00',
    ]);

    insertExceptionLog([
        'key' => 'fefefefe22222222222222222222222222222222222222222222222222222222',
        'name' => 'InvalidArgumentException',
        'message' => 'Delete me too.',
        'file' => '/var/www/app/DeleteService.php',
        'line' => 20,
        'raw_exception' => 'Delete me too',
        'latest_at' => '2026-03-25 11:30:00',
    ]);

    $response = $this
        ->withSession(['_token' => 'test-token'])
        ->post('/exception-viewer/purge', [
            '_token' => 'test-token',
            'redirect_to' => '/exception-viewer',
        ]);

    $response->assertRedirect('/exception-viewer');
    expect(DB::table('exception_logs')->count())->toBe(0);
});
