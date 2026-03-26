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

Publish everything in one shot:

```bash
php artisan vendor:publish --provider="Korioinc\ExceptionViewer\ExceptionViewerServiceProvider"
php artisan migrate
```

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

Repeated exceptions increment `count` and refresh the latest exception text and context fields.

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
- purge action for clearing `exception_logs`

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

Alarm dispatch always uses a queued job.

If the host app uses:

```env
QUEUE_CONNECTION=sync
```

the alarm job still runs in the same process.

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

If you use Redis plus Horizon, Discord delivery happens in Horizon workers.

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

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Credits

- [korioinc](https://github.com/korioinc)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
