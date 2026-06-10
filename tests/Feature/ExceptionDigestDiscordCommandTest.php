<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Korioinc\ExceptionViewer\Commands\ExceptionDigestDiscordCommand;

beforeEach(function () {
    DB::table('exception_logs')->delete();
    Carbon::setTestNow('2026-03-25 12:00:00');
    config()->set('exception-viewer.digest_discord_webhook_url', '');
});

afterEach(function () {
    Carbon::setTestNow();
    config()->set('exception-viewer.database_connection', null);
});

function insertDigestCommandExceptionLog(array $attributes, ?string $connection = null): void
{
    $createdAt = Carbon::parse($attributes['created_at']);
    $latestAt = Carbon::parse($attributes['latest_at']);
    $sourceKey = array_key_exists('source_key', $attributes)
        ? $attributes['source_key']
        : 'local-app';

    $query = $connection === null
        ? DB::table('exception_logs')
        : DB::connection($connection)->table('exception_logs');

    $query->insert([
        'source_key' => $sourceKey,
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
        'latest_at' => $latestAt,
        'created_at' => $createdAt,
        'updated_at' => $latestAt,
    ]);
}

function registerDigestCommandForHostApplication(): void
{
    app(Kernel::class)->registerCommand(app(ExceptionDigestDiscordCommand::class));
}

it('fails without sending a request when the discord webhook is empty', function () {
    registerDigestCommandForHostApplication();

    Http::fake();

    $this->artisan('exception-viewer:discord-digest')
        ->expectsOutputToContain('Discord digest webhook is not configured.')
        ->assertExitCode(1);

    Http::assertSentCount(0);
});

it('groups rows by source with previous and new markers without command options', function () {
    registerDigestCommandForHostApplication();

    config()->set('exception-viewer.digest_discord_webhook_url', 'https://discord.test/digest');

    Http::fake([
        'discord.test/*' => Http::response(['ok' => true], 204),
    ]);

    insertDigestCommandExceptionLog([
        'key' => 'today-new-error-key',
        'message' => 'Today new exception message',
        'created_at' => '2026-03-25 11:20:00',
        'latest_at' => '2026-03-25 11:45:00',
    ]);

    insertDigestCommandExceptionLog([
        'key' => 'today-repeated-error-key',
        'source_key' => 'remote-app',
        'message' => 'Today repeated exception message',
        'count' => 7,
        'created_at' => '2026-03-25 09:00:00',
        'latest_at' => '2026-03-25 11:15:00',
    ]);

    insertDigestCommandExceptionLog([
        'key' => 'previous-date-single-error-key',
        'name' => LogicException::class,
        'message' => 'Previous local exception message',
        'count' => 3,
        'created_at' => '2026-03-24 09:00:00',
        'latest_at' => '2026-03-25 10:30:00',
    ]);

    $this->artisan('exception-viewer:discord-digest')
        ->expectsOutputToContain('[Daily] Exception Digest')
        ->expectsOutputToContain('[local-app]')
        ->expectsOutputToContain('[remote-app]')
        ->expectsOutputToContain('< LogicException (3)')
        ->expectsOutputToContain('> RuntimeException (1)')
        ->expectsOutputToContain('> RuntimeException (7)')
        ->doesntExpectOutputToContain('Previous local exception message')
        ->doesntExpectOutputToContain('Today new exception message')
        ->doesntExpectOutputToContain('Today repeated exception message')
        ->expectsOutputToContain('Exception digest sent to Discord.')
        ->assertExitCode(0);

    Http::assertSentCount(1);
    Http::assertSent(function ($request) {
        $data = $request->data();
        $description = (string) ($data['embeds'][0]['description'] ?? '');

        $localSourcePosition = strpos($description, '[local-app]');
        $remoteSourcePosition = strpos($description, '[remote-app]');
        $previousLinePosition = strpos($description, '< LogicException (3)');
        $localNewLinePosition = strpos($description, '> RuntimeException (1)');
        $remoteEmptyLinePosition = strpos($description, '< No previous errors.');
        $remoteNewLinePosition = strpos($description, '> RuntimeException (7)');
        $localSeparatorPosition = $previousLinePosition === false
            ? false
            : strpos($description, PHP_EOL.'----------------------'.PHP_EOL, $previousLinePosition);

        return $request->url() === 'https://discord.test/digest'
            && $localSourcePosition !== false
            && $remoteSourcePosition !== false
            && $previousLinePosition !== false
            && $localNewLinePosition !== false
            && $remoteEmptyLinePosition !== false
            && $remoteNewLinePosition !== false
            && $localSeparatorPosition !== false
            && $localSourcePosition < $previousLinePosition
            && $previousLinePosition < $localSeparatorPosition
            && $localSeparatorPosition < $localNewLinePosition
            && $localNewLinePosition < $remoteSourcePosition
            && $remoteSourcePosition < $remoteEmptyLinePosition
            && $remoteEmptyLinePosition < $remoteNewLinePosition
            && ! str_contains($description, 'Previous local exception message')
            && ! str_contains($description, 'Today new exception message')
            && ! str_contains($description, 'Today repeated exception message');
    });
});

it('uses one day cutoff for the whole digest run', function () {
    registerDigestCommandForHostApplication();

    config()->set('exception-viewer.digest_discord_webhook_url', 'https://discord.test/digest');

    Http::fake([
        'discord.test/*' => Http::response(['ok' => true], 204),
    ]);

    insertDigestCommandExceptionLog([
        'key' => 'midnight-boundary-key',
        'message' => 'Midnight boundary exception',
        'created_at' => '2026-03-25 10:00:00',
        'latest_at' => '2026-03-25 23:10:00',
    ]);

    $nowCalls = 0;
    Carbon::setTestNow(function () use (&$nowCalls) {
        $nowCalls++;

        return Carbon::parse($nowCalls === 1
            ? '2026-03-25 23:59:59'
            : '2026-03-26 00:00:01');
    });

    $this->artisan('exception-viewer:discord-digest')
        ->assertExitCode(0);

    expect($nowCalls)->toBe(1);

    Http::assertSent(function ($request) {
        $data = $request->data();
        $description = (string) ($data['embeds'][0]['description'] ?? '');

        return str_contains($description, 'Summary (2026-03-25 23:59:59)')
            && str_contains($description, '[local-app]')
            && str_contains($description, '< No previous errors.')
            && str_contains($description, '> RuntimeException (1)')
            && ! str_contains($description, '< RuntimeException (1)')
            && ! str_contains($description, 'Midnight boundary exception');
    });
});

it('uses the configured exception log database connection', function () {
    registerDigestCommandForHostApplication();

    $secondaryPath = tempnam(sys_get_temp_dir(), 'exception-viewer-digest-');

    expect($secondaryPath)->not->toBeFalse();

    config()->set('database.connections.digest_logs', [
        'driver' => 'sqlite',
        'database' => $secondaryPath,
        'prefix' => '',
    ]);
    config()->set('exception-viewer.database_connection', 'digest_logs');

    DB::purge('digest_logs');

    $migration = include __DIR__.'/../../database/migrations/create_exception_logs_table.php.stub';

    try {
        $migration->up();

        insertDigestCommandExceptionLog([
            'key' => 'custom-connection-key',
            'message' => 'Custom connection exception',
            'created_at' => '2026-03-25 11:30:00',
            'latest_at' => '2026-03-25 11:45:00',
        ], 'digest_logs');

        config()->set('exception-viewer.digest_discord_webhook_url', 'https://discord.test/digest');

        Http::fake([
            'discord.test/*' => Http::response(['ok' => true], 204),
        ]);

        $this->artisan('exception-viewer:discord-digest')
            ->expectsOutputToContain('> RuntimeException (1)')
            ->assertExitCode(0);
    } finally {
        Schema::connection('digest_logs')->dropIfExists('exception_logs');
        DB::purge('digest_logs');
        config()->set('exception-viewer.database_connection', null);
        @unlink($secondaryPath);
    }
});

it('sends one discord webhook request with the rendered digest when configured', function () {
    registerDigestCommandForHostApplication();

    config()->set('exception-viewer.digest_discord_webhook_url', 'https://discord.test/digest');

    Http::fake([
        'discord.test/*' => Http::response(['ok' => true], 204),
    ]);

    insertDigestCommandExceptionLog([
        'key' => 'discord-new-key',
        'message' => 'Discord digest exception',
        'created_at' => '2026-03-25 11:20:00',
        'latest_at' => '2026-03-25 11:30:00',
    ]);

    $this->artisan('exception-viewer:discord-digest')
        ->expectsOutputToContain('Exception digest sent to Discord.')
        ->assertExitCode(0);

    Http::assertSentCount(1);
    Http::assertSent(function ($request) {
        $data = $request->data();
        $embed = $data['embeds'][0] ?? [];
        $description = (string) ($embed['description'] ?? '');

        return $request->url() === 'https://discord.test/digest'
            && ($embed['title'] ?? null) === 'Exception Digest'
            && str_contains($description, '[Daily] Exception Digest')
            && str_contains($description, 'Summary (2026-03-25 12:00:00)')
            && str_contains($description, 'Name')
            && str_contains($description, 'Prev errors')
            && str_contains($description, 'New errors')
            && ! str_contains($description, '| name ')
            && ! str_contains($description, '| previous errors | new errors |')
            && preg_match('/[\x{AC00}-\x{D7AF}]/u', $description) !== 1
            && str_contains($description, '[local-app]')
            && str_contains($description, '< No previous errors.')
            && str_contains($description, PHP_EOL.'----------------------'.PHP_EOL)
            && str_contains($description, '> RuntimeException (1)')
            && ! str_contains($description, 'Discord digest exception')
            && ! str_contains($description, '| Source')
            && str_contains($description, '+');
    });
});

it('splits oversized discord digest across requests within embed description limits', function () {
    registerDigestCommandForHostApplication();

    config()->set('exception-viewer.digest_discord_webhook_url', 'https://discord.test/digest');

    $descriptions = [];

    Http::fake(function ($request) use (&$descriptions) {
        $data = $request->data();
        $descriptions[] = (string) ($data['embeds'][0]['description'] ?? '');

        return Http::response(['ok' => true], 204);
    });

    for ($index = 1; $index <= 260; $index++) {
        $latestAt = Carbon::parse('2026-03-25 11:00:00')->subSeconds($index);

        insertDigestCommandExceptionLog([
            'key' => 'oversized-digest-key-'.$index,
            'created_at' => '2026-03-25 08:00:00',
            'latest_at' => $latestAt->format('Y-m-d H:i:s'),
        ]);
    }

    $this->artisan('exception-viewer:discord-digest')
        ->expectsOutputToContain('Exception digest sent to Discord.')
        ->assertExitCode(0);

    expect(count($descriptions))->toBeGreaterThan(1)
        ->and(max(array_map('strlen', $descriptions)))->toBeLessThanOrEqual(4096)
        ->and(substr_count(implode(PHP_EOL, $descriptions), '> RuntimeException (1)'))->toBe(260);
});

it('omits previous error details before splitting an oversized discord digest', function () {
    registerDigestCommandForHostApplication();

    config()->set('exception-viewer.digest_discord_webhook_url', 'https://discord.test/digest');

    $descriptions = [];

    Http::fake(function ($request) use (&$descriptions) {
        $data = $request->data();
        $descriptions[] = (string) ($data['embeds'][0]['description'] ?? '');

        return Http::response(['ok' => true], 204);
    });

    for ($index = 1; $index <= 260; $index++) {
        $latestAt = Carbon::parse('2026-03-25 10:00:00')->subSeconds($index);

        insertDigestCommandExceptionLog([
            'key' => 'previous-priority-key-'.$index,
            'name' => LogicException::class,
            'created_at' => '2026-03-24 08:00:00',
            'latest_at' => $latestAt->format('Y-m-d H:i:s'),
        ]);
    }

    insertDigestCommandExceptionLog([
        'key' => 'new-priority-key',
        'created_at' => '2026-03-25 11:00:00',
        'latest_at' => '2026-03-25 11:30:00',
    ]);

    $this->artisan('exception-viewer:discord-digest')
        ->expectsOutputToContain('Exception digest sent to Discord.')
        ->assertExitCode(0);

    $payload = implode(PHP_EOL, $descriptions);

    expect($descriptions)->toHaveCount(1)
        ->and(max(array_map('strlen', $descriptions)))->toBeLessThanOrEqual(4096)
        ->and($payload)->toContain('Previous errors omitted')
        ->and($payload)->not->toContain('< LogicException (1)')
        ->and($payload)->toContain('> RuntimeException (1)');
});

it('fails safely when the configured discord webhook returns an error', function () {
    registerDigestCommandForHostApplication();

    config()->set('exception-viewer.digest_discord_webhook_url', 'https://discord.test/digest');

    Http::fake([
        'discord.test/*' => Http::response('nope', 500),
    ]);

    $this->artisan('exception-viewer:discord-digest')
        ->expectsOutputToContain('Failed to send exception digest to Discord (HTTP 500).')
        ->doesntExpectOutputToContain('https://discord.test/digest')
        ->assertExitCode(1);
});

it('fails safely when the configured discord webhook request throws', function () {
    registerDigestCommandForHostApplication();

    config()->set('exception-viewer.digest_discord_webhook_url', 'https://discord.test/digest');

    Http::fake(fn () => throw new RuntimeException('Discord unavailable'));

    $this->artisan('exception-viewer:discord-digest')
        ->expectsOutputToContain('Failed to send exception digest to Discord: Discord unavailable')
        ->doesntExpectOutputToContain('https://discord.test/digest')
        ->assertExitCode(1);
});

it('does not expose raw exception context or request data in the discord digest', function () {
    registerDigestCommandForHostApplication();

    config()->set('exception-viewer.digest_discord_webhook_url', 'https://discord.test/digest');

    Http::fake([
        'discord.test/*' => Http::response(['ok' => true], 204),
    ]);

    insertDigestCommandExceptionLog([
        'key' => 'privacy-key',
        'message' => 'Safe digest exception message',
        'raw_exception' => 'raw-secret-stack-token',
        'request_headers' => json_encode(['authorization' => 'header-secret-token']),
        'request_payload' => json_encode(['password' => 'payload-secret-token']),
        'created_at' => '2026-03-25 11:20:00',
        'latest_at' => '2026-03-25 11:30:00',
    ]);

    $this->artisan('exception-viewer:discord-digest')
        ->assertExitCode(0);

    Http::assertSent(function ($request) {
        $payload = json_encode($request->data());

        return $request->url() === 'https://discord.test/digest'
            && is_string($payload)
            && str_contains($payload, 'RuntimeException')
            && ! str_contains($payload, 'Safe digest exception message')
            && ! str_contains($payload, 'raw-secret-stack-token')
            && ! str_contains($payload, 'header-secret-token')
            && ! str_contains($payload, 'payload-secret-token');
    });
});

it('does not auto register the digest command with the package provider', function () {
    expect(Artisan::all())->not->toHaveKey('exception-viewer:discord-digest');
});

it('does not register the digest command with the package scheduler', function () {
    $events = collect(app(Schedule::class)->events());

    expect($events->contains(function ($event) {
        return str_contains((string) $event->command, 'exception-viewer:prune')
            && $event->expression === '0 0 * * *';
    }))->toBeTrue()
        ->and($events->contains(function ($event) {
            return str_contains((string) $event->command, 'exception-viewer:discord-digest');
        }))->toBeFalse();
});
