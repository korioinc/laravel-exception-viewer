<?php

use Korioinc\ExceptionViewer\Http\Middleware\DenyInProduction;

return [
    /*
    |--------------------------------------------------------------------------
    | Exception Viewer
    |--------------------------------------------------------------------------
    | Master switch for the package. When disabled, exceptions are not
    | recorded and alarms are not evaluated.
    |
    */

    'enabled' => env('EL_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Database Connection
    |--------------------------------------------------------------------------
    | The database connection used for the `exception_logs` table.
    | Leave this as `null` to use the application's default connection.
    |
    */

    'database_connection' => null,

    /*
    |--------------------------------------------------------------------------
    | Source Identity
    |--------------------------------------------------------------------------
    | Stable identity for the service writing or forwarding exceptions.
    | `key` is required when outbound forwarding is enabled.
    |
    */

    'source' => [
        'key' => env('EL_SOURCE_KEY', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | Central Forwarding
    |--------------------------------------------------------------------------
    | When enabled, exceptions are still stored locally first and then a
    | queued job forwards the stored snapshot to a central receiver endpoint.
    |
    */

    'forwarding' => [
        'enabled' => env('EL_FORWARDING_ENABLED', false),
        'endpoint' => env('EL_FORWARDING_ENDPOINT', ''),
        'api_key' => env('EL_FORWARDING_API_KEY', ''),
        'queue' => env('EL_FORWARDING_QUEUE', null),
        'timeout' => (float) env('EL_FORWARDING_TIMEOUT', 2),
        'tries' => (int) env('EL_FORWARDING_TRIES', 3),
        'backoff' => env('EL_FORWARDING_BACKOFF', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Central Receiver API
    |--------------------------------------------------------------------------
    | Disabled by default. Configure accepted API keys as a comma-separated
    | `EL_RECEIVER_API_KEYS` value or override this config with an array.
    |
    */

    'receiver' => [
        'enabled' => env('EL_RECEIVER_ENABLED', false),
        'route_path' => env('EL_RECEIVER_ROUTE_PATH', 'exception-viewer/api/exceptions'),
        'api_keys' => env('EL_RECEIVER_API_KEYS', ''),
        'middleware' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Alarm
    |--------------------------------------------------------------------------
    | Discord alarms are evaluated only for recorded exception events.
    | Alarms are grouped by exception fingerprint and are rate-limited by
    | the time frame and delay settings below.
    |
    */

    'alarm_enabled' => env('EL_ALARM_ENABLED', env('EL_ALARM_ENALBED', true)),
    'log_time_frame' => (int) env('EL_LOG_TIME_FRAME', 3),
    'log_per_time_frame' => (int) env('EL_LOG_PER_TIME_FRAME', 2),
    'delay_between_alarms' => (int) env('EL_DELAY_BETWEEN_ALARMS', 5),

    /*
    | Optional fixed message for Discord alarms.
    | When empty, the package sends the exception details instead.
    */
    'notification_message' => env('EL_NOTIFICATION_MESSAGE', ''),

    /*
    | Discord webhook destination for queued alarm delivery.
    | If this is empty, alarm jobs are not dispatched.
    */
    'discord_webhook_url' => env('EL_DISCORD_WEBHOOK_URL', ''),

    /*
    | Embed title used for Discord alarm messages.
    */
    'notification_title' => 'Log Alarm Notification',

    /*
    |--------------------------------------------------------------------------
    | Viewer Route
    |--------------------------------------------------------------------------
    | Route path, asset path, and middleware used by the Blade viewer.
    | `assets_path` is exposed to the frontend as the asset base URL.
    |
    */

    'route_path' => 'exception-viewer',
    'assets_path' => 'vendor/exception-viewer',
    'middleware' => [
        'web',
        DenyInProduction::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Request Context
    |--------------------------------------------------------------------------
    | Controls whether HTTP request metadata should be stored alongside
    | each exception, which keys must be masked, and optional size limits
    | for serialized headers and payloads.
    |
    */

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
