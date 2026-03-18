<?php

declare(strict_types=1);

use Andydefer\LaravelReminder\Enums\ToleranceUnit;

return [
    /*
    |--------------------------------------------------------------------------
    | Default Tolerance
    |--------------------------------------------------------------------------
    |
    | The default tolerance window for sending reminders. This can be
    | overridden by individual models implementing ShouldRemind.
    |
    */
    'default_tolerance' => [
        'value' => 30,
        'unit' => ToleranceUnit::MINUTE,
    ],

    /*
    |--------------------------------------------------------------------------
    | Maximum Attempts
    |--------------------------------------------------------------------------
    |
    | Maximum number of attempts to send a reminder before marking as failed.
    |
    */
    'max_attempts' => 3,

    /*
    |--------------------------------------------------------------------------
    | Queue Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the queue settings for processing reminders.
    |
    */
    'queue' => [
        'enabled' => env('REMINDER_QUEUE_ENABLED', true),
        'name' => env('REMINDER_QUEUE_NAME', 'default'),
        'connection' => env('REMINDER_QUEUE_CONNECTION', env('QUEUE_CONNECTION', 'sync')),
    ],

    /*
    |--------------------------------------------------------------------------
    | Schedule Frequency
    |--------------------------------------------------------------------------
    |
    | How often the reminder job should run (in seconds).
    | Default is 15 seconds as specified in the requirement.
    |
    */
    'schedule_frequency' => 15, // seconds

    /*
    |--------------------------------------------------------------------------
    | Logging Configuration
    |--------------------------------------------------------------------------
    |
    | Configure logging behavior for the reminder system.
    |
    */
    'logging' => [
        'enabled' => env('REMINDER_LOGGING_ENABLED', true),
        'channel' => env('REMINDER_LOG_CHANNEL', env('LOG_CHANNEL', 'stack')),
        'level' => env('REMINDER_LOG_LEVEL', 'debug'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cleanup Configuration
    |--------------------------------------------------------------------------
    |
    | Automatically clean up old sent/failed reminders after X days.
    | Set to 0 to disable cleanup.
    |
    */
    'cleanup' => [
        'enabled' => env('REMINDER_CLEANUP_ENABLED', true),
        'after_days' => env('REMINDER_CLEANUP_AFTER_DAYS', 30),
    ],
];
