# Laravel Exception Viewer

Laravel Exception Viewer keeps Laravel's native exception reporting flow intact, stores aggregated exception rows in `exception_logs`, ships with a Blade viewer, and exposes markdown endpoints that work well with `curl`, automation, and LLM workflows.

## What It Does

- Records exceptions that reach Laravel's exception reporting flow
- Aggregates repeated exceptions into one fingerprinted row
- Stores the latest exception text plus masked context
- Captures HTTP exceptions
- Captures CLI exceptions
- Captures queued job exceptions, including job metadata and payload
- Provides a Blade viewer at `/exception-viewer`
- Provides markdown export endpoints for one exception or all exceptions
- Can dispatch Discord alarm jobs for repeated exceptions

## Installation

Requirements:

- PHP 8.2+
- Laravel 11, 12, or 13

Install the package with Composer:

```bash
composer require korioinc/laravel-exception-viewer
```

Laravel auto-discovers the package service provider, so no manual provider registration is required.

Publish the default install set in one shot:

```bash
php artisan vendor:publish --tag="exception-viewer-install"
php artisan migrate
```

This publishes:

- config
- migrations

Or publish only what you need:

```bash
php artisan vendor:publish --tag="exception-viewer-migrations"
php artisan migrate
```

```bash
php artisan vendor:publish --tag="exception-viewer-config"
```

```bash
php artisan vendor:publish --tag="exception-viewer-views"
```

If you explicitly want every publishable artifact registered by the provider, including views, you can still use:

```bash
php artisan vendor:publish --provider="Korioinc\ExceptionViewer\ExceptionViewerServiceProvider"
```

Published views are placed at:

```text
resources/views/vendor/exception-viewer/pages/index.blade.php
```

If you store `exception_logs` on a dedicated database connection, set `exception-viewer.database_connection` before running `php artisan migrate`. The published migration uses that configured connection when it creates or drops the table.

## Configuration

Published config:

```php
use Korioinc\ExceptionViewer\Http\Middleware\DenyInProduction;

return [
    'enabled' => env('EL_ENABLED', true),
    'database_connection' => null,

    'source' => [
        'key' => env('EL_SOURCE_KEY', ''),
    ],

    'forwarding' => [
        'enabled' => env('EL_FORWARDING_ENABLED', false),
        'mode' => env('EL_FORWARDING_MODE', 'sync'),
        'endpoint' => env('EL_FORWARDING_ENDPOINT', ''),
        'api_key' => env('EL_FORWARDING_API_KEY', ''),
        'queue' => env('EL_FORWARDING_QUEUE', null),
        'timeout' => (float) env('EL_FORWARDING_TIMEOUT', 2),
        'tries' => (int) env('EL_FORWARDING_TRIES', 3),
        'backoff' => env('EL_FORWARDING_BACKOFF', 60),
    ],

    'receiver' => [
        'enabled' => env('EL_RECEIVER_ENABLED', false),
        'route_path' => env('EL_RECEIVER_ROUTE_PATH', 'api/exception-viewer/exceptions'),
        'api_keys' => env('EL_RECEIVER_API_KEYS', ''),
        'middleware' => ['api'],
    ],

    'alarm_enabled' => env('EL_ALARM_ENABLED', env('EL_ALARM_ENALBED', true)),
    'log_time_frame' => (int) env('EL_LOG_TIME_FRAME', 3),
    'log_per_time_frame' => (int) env('EL_LOG_PER_TIME_FRAME', 2),
    'delay_between_alarms' => (int) env('EL_DELAY_BETWEEN_ALARMS', 5),
    'notification_message' => env('EL_NOTIFICATION_MESSAGE', ''),
    'discord_webhook_url' => env('EL_DISCORD_WEBHOOK_URL', ''),
    'notification_title' => 'Log Alarm Notification',

    'route_path' => 'exception-viewer',
    'assets_path' => 'vendor/exception-viewer',
    'middleware' => [
        'web',
        DenyInProduction::class,
    ],

    'request_context' => [
        'enabled' => true,
        'masked_keys' => [
            'authorization',
            'x-api-key',
            'password',
        ],
        'max_headers_size' => null,
        'max_payload_size' => null,
    ],
];
```

Key options:

- `enabled`: master switch for recording and alarm evaluation
- `database_connection`: optional connection for `exception_logs`; `null` uses the app default, and the published migration uses this connection too
- `source.key`: stable service identity used for central forwarding; required when forwarding is enabled
- `forwarding.enabled`: stores locally first, then forwards to the central receiver when all forwarding settings are present
- `forwarding.mode`: `sync` sends the HTTP request during exception reporting, while `queue` dispatches a forwarding job
- `forwarding.endpoint`, `forwarding.api_key`: central receiver URL and bearer token
- `forwarding.queue`, `forwarding.timeout`, `forwarding.tries`, `forwarding.backoff`: queue and HTTP delivery controls
- `receiver.enabled`: opens the central machine-to-machine receiver endpoint when true
- `receiver.route_path`, `receiver.api_keys`, `receiver.middleware`: central route path, accepted bearer keys, and API middleware
- `route_path`: viewer route prefix
- `assets_path`: asset base path exposed to the Blade viewer
- `middleware`: viewer route middleware stack; default is `['web', DenyInProduction::class]`
- `request_context.enabled`: enables request or execution context capture
- `request_context.masked_keys`: keys masked before headers or payload are stored; the default list is `authorization`, `x-api-key`, and `password`
- `request_context.max_headers_size`, `request_context.max_payload_size`: optional truncation limits

Alarm delivery and cache failures are swallowed so the package never interrupts Laravel's native exception reporting flow.

## Recording Model

The package records one aggregated row per exception fingerprint in `exception_logs`.
Only exceptions that reach Laravel's exception reporting flow are recorded.

Stored columns:

- `source_key`
- `received_at`
- `key`
- `name`
- `message`
- `file`
- `line`
- `raw_exception`
- `request_method`
- `request_endpoint`
- `request_headers`
- `request_payload`
- `count`
- `latest_at`

Fingerprinting currently uses:

- exception class
- exception message
- file
- line
- the first part of the stack trace

Repeated local exceptions increment `count` and refresh the latest exception text and context fields. The central receiver stores aggregate snapshots by `source_key` plus `key`, so two services can report the same fingerprint key without overwriting each other.

## Captured Context

The package records different execution contexts:

- HTTP exception
  - `request_method`: HTTP method such as `GET` or `POST`
  - `request_endpoint`: request path or URL
  - `request_headers`: masked request headers
  - `request_payload`: masked request payload
- CLI exception
  - request-specific fields can be empty because there is no HTTP request
- Job exception
  - `request_method`: `JOB`
  - `request_endpoint`: queued job class name
  - `request_headers`: queue metadata such as `queue`, `attempts`, `job_id`, `job_name`
  - `request_payload`: masked job payload
  - Synchronous jobs dispatched during an HTTP request are still recorded as job exceptions, not as HTTP request exceptions

By default, `authorization`, `x-api-key`, and `password` are masked before storage. Add keys such as `token`, `secret`, `cookie`, or `set-cookie` to `request_context.masked_keys` if your app needs them masked too.

## Viewer

By default, the Blade viewer is available at:

```text
/exception-viewer
```

The default middleware stack includes `DenyInProduction`, so the viewer returns `404` in `production` unless you explicitly override the middleware.

In shared non-production environments such as staging, add your own access control middleware like `auth` or an internal allowlist. By default, the package does not add authentication on top of `web`.

The viewer includes:

- left sidebar grouped by exception `name`
- latest-first aggregated exception list
- expandable detail rows
- copy button for markdown output
- link copy button for one exception
- all-export copy button
- purge action for clearing the current source, plus a separate all-source clear action

Markdown endpoints:

```text
/exception-viewer/all
/exception-viewer/{key}
```

These endpoints return `text/markdown`.

## Markdown Output

The single-exception endpoint returns a markdown document shaped like this:

```md
# Exception

- Name: `RuntimeException`
- Message: Example failure while processing checkout
- File: `/var/www/html/app/Services/CheckoutService.php`
- Line: 184

## Request

- Method: POST
- Endpoint: /api/checkout

## Headers

~~~json
{"authorization":"[MASKED]","x-request-id":"trace-123"}
~~~

## Payload

~~~json
{"order_id":1001,"password":"[MASKED]"}
~~~

## Context

~~~text
[2026-03-26 10:00:00] stack.ERROR: Example failure while processing checkout ...
~~~
```

If request context is missing, the request-related sections are omitted entirely.

Markdown output includes the source key when an exception row has one.

## Central Receiver

Install the package on every source service and on one central bridge service.

Source services keep writing to their own `exception_logs` table. When
forwarding is enabled, they also send a snapshot to the central bridge.

### Source Service

Set these values on each service that sends exceptions:

```env
EL_SOURCE_KEY=service-a
EL_FORWARDING_ENABLED=true
EL_FORWARDING_ENDPOINT=https://central.example.com/api/exception-viewer/exceptions
EL_FORWARDING_API_KEY=service-a-secret
```

`EL_SOURCE_KEY` is the source name shown in the central viewer. The central
database stores this key only.

`EL_FORWARDING_API_KEY` must be one of the keys configured on the central
bridge service.

The default `EL_FORWARDING_MODE=sync` performs the central HTTP request
immediately after local exception storage. Delivery failures are swallowed so
Laravel's native exception flow is not interrupted, but they are not retried by
the package.

For services with queue workers, use asynchronous forwarding:

```env
EL_FORWARDING_MODE=queue
```

### Central Bridge Service

Set these values on the service that receives forwarded exceptions:

```env
EL_RECEIVER_ENABLED=true
EL_RECEIVER_API_KEYS=service-a-secret,service-b-secret
```

The receiver URL is:

```text
https://central.example.com/api/exception-viewer/exceptions
```

The source service sends `EL_FORWARDING_API_KEY` as a bearer token. The central
bridge accepts it when it is listed in `EL_RECEIVER_API_KEYS`.

View received exceptions at:

```text
https://central.example.com/exception-viewer
```

Receiver API:

```text
POST /api/exception-viewer/exceptions
Authorization: Bearer <api-key>
Content-Type: application/json
Accept: application/json
```

Accepted payloads return `202` with `accepted`, `source_key`, `key`, and `count`. Missing or invalid API keys return `401`. Malformed payloads or unsupported payload versions return `422`. When receiver is disabled, the endpoint returns `404`.

The central viewer shows per-source tabs and defaults to the local source. Markdown exports continue to work and include the source key for source-aware rows.

Security notes:

- Keep `EL_RECEIVER_API_KEYS` and `EL_FORWARDING_API_KEY` in secret storage.
- Prefer one receiver key per source so a single service can be rotated independently.
- Do not put the receiver route behind CSRF-only `web` middleware; it is a JSON machine-to-machine endpoint.
- Protect the central viewer separately with application auth or internal access controls before using it in production.
- Forwarding uses the already stored request context, so configured `request_context.masked_keys` apply before remote delivery.

## LLM Workflow

The markdown endpoints are designed to work with `curl`, scripts, and LLM tools.

Use all exceptions when you want broad triage:

```text
http://localhost/exception-viewer/all
```

Use one exception when you want focused analysis:

```text
http://localhost/exception-viewer/629a80482b8e84f9412715b427b8a1d9db08845ba59071352d9f557d444dc2db
```

Example `curl` usage:

```bash
curl http://localhost/exception-viewer/all
```

```bash
curl http://localhost/exception-viewer/629a80482b8e84f9412715b427b8a1d9db08845ba59071352d9f557d444dc2db
```

Example prompt for an LLM:

```text
Read this exception export and explain the root cause, likely blast radius, and the smallest safe fix:
http://localhost/exception-viewer/all
```

For a single issue:

```text
Read this exception detail and propose a fix with verification steps:
http://localhost/exception-viewer/629a80482b8e84f9412715b427b8a1d9db08845ba59071352d9f557d444dc2db
```

If your app is not running on `localhost`, replace the host and port with the actual viewer URL.

## Alarm

If `EL_ALARM_ENABLED=true` and `EL_DISCORD_WEBHOOK_URL` is configured, the package can dispatch a Discord alarm job.

Supported env keys:

```env
EL_ENABLED=true
EL_ALARM_ENABLED=true
EL_LOG_TIME_FRAME=3
EL_LOG_PER_TIME_FRAME=2
EL_DELAY_BETWEEN_ALARMS=5
EL_NOTIFICATION_MESSAGE=
EL_DISCORD_WEBHOOK_URL=
```

Alarm behavior:

- only exceptions handled by this package are considered
- alarms are grouped by exception fingerprint
- alarms are sent immediately while the fingerprint is below the configured limit
- once the same fingerprint has been sent `EL_LOG_PER_TIME_FRAME` times within `EL_LOG_TIME_FRAME` minutes, further alarms are blocked for `EL_DELAY_BETWEEN_ALARMS` minutes
- the delay is applied per fingerprint
- alarm dispatch always happens through a queued job
- Discord delivery is optional and never interrupts Laravel's native exception flow
- if `notification_message` is empty, the package sends `LOG_LEVEL`, `LOG_MESSAGE`, `LOG_FILE`, and `LOG_LINE`
- alarm messages always include an `Open in Viewer` detail link; whether that URL is reachable depends on your viewer middleware configuration

Example with the current defaults:

- up to 2 alarms can be sent within 3 minutes for the same fingerprint
- after that, the same fingerprint is muted for 5 minutes

## Queue and Async Delivery

Alarm dispatch uses queued jobs. Central forwarding uses queued jobs when
`EL_FORWARDING_MODE=queue` and sends inline when `EL_FORWARDING_MODE=sync`.

If the host app uses:

```env
QUEUE_CONNECTION=sync
```

queued jobs still run in the same process.

For real async delivery, use a non-`sync` queue such as:

```env
QUEUE_CONNECTION=redis
```

Then run a worker or Horizon:

```bash
php artisan horizon
```

or

```bash
php artisan queue:work
```

If you use Redis plus Horizon, Discord delivery and central forwarding happen in Horizon workers.

## Publish Commands

Publish all package assets:

```bash
php artisan vendor:publish --provider="Korioinc\ExceptionViewer\ExceptionViewerServiceProvider"
```

Or publish individual assets:

```bash
php artisan vendor:publish --tag="exception-viewer-config"
php artisan vendor:publish --tag="exception-viewer-views"
php artisan vendor:publish --tag="exception-viewer-migrations"
```

## Testing

```bash
composer test
```

## Local Development Data

Refresh the Testbench database and seed representative exception rows:

```bash
composer dev:fresh
composer dev:seed
```

The seeder inserts local HTTP, local queue, and forwarded source samples into `exception_logs`.

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Credits

- [korioinc](https://github.com/korioinc)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
