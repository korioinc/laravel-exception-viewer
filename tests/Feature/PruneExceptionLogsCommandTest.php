<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    DB::table('exception_logs')->delete();
    Carbon::setTestNow('2026-03-25 12:00:00');
});

afterEach(function () {
    Carbon::setTestNow();
});

function insertPruneCommandExceptionLog(array $attributes): void
{
    $timestamp = Carbon::parse($attributes['latest_at']);

    DB::table('exception_logs')->insert([
        'source_key' => $attributes['source_key'] ?? 'local-app',
        'received_at' => $attributes['received_at'] ?? null,
        'key' => $attributes['key'],
        'name' => $attributes['name'] ?? RuntimeException::class,
        'message' => $attributes['message'] ?? 'Runtime exploded.',
        'file' => $attributes['file'] ?? '/var/www/app/RuntimeService.php',
        'line' => $attributes['line'] ?? 10,
        'raw_exception' => $attributes['raw_exception'] ?? 'Runtime exploded.',
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

it('deletes exception logs whose latest occurrence is at least fourteen days old', function () {
    insertPruneCommandExceptionLog([
        'key' => 'old-exactly-fourteen-days',
        'latest_at' => '2026-03-11 12:00:00',
    ]);

    insertPruneCommandExceptionLog([
        'key' => 'old-more-than-fourteen-days',
        'latest_at' => '2026-03-11 11:59:59',
    ]);

    insertPruneCommandExceptionLog([
        'key' => 'recent-less-than-fourteen-days',
        'latest_at' => '2026-03-11 12:00:01',
    ]);

    $this->artisan('exception-viewer:prune')
        ->assertExitCode(0);

    expect(DB::table('exception_logs')->pluck('key')->all())->toBe([
        'recent-less-than-fourteen-days',
    ]);
});

it('registers the prune command with the scheduler', function () {
    $events = collect(app(Schedule::class)->events());

    expect($events->contains(function ($event) {
        return str_contains((string) $event->command, 'exception-viewer:prune')
            && $event->expression === '0 0 * * *';
    }))->toBeTrue();
});
