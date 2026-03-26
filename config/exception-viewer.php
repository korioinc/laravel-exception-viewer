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
