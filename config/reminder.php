<?php

declare(strict_types=1);

use Andydefer\LaravelReminder\Enums\ToleranceUnit;

return [
    /*
    |--------------------------------------------------------------------------
    | Default Tolerance
    |--------------------------------------------------------------------------
    |
    | This value defines the default tolerance window for all remindable models.
    | Each model can override this value by implementing the getTolerance() method.
    |
    */
    'default_tolerance' => [
        'value' => 30,
        'unit' => ToleranceUnit::MINUTE, // MINUTE, HOUR, DAY, WEEK, MONTH, YEAR
    ],

    /*
    |--------------------------------------------------------------------------
    | Maximum Attempts
    |--------------------------------------------------------------------------
    |
    | This value determines how many times the system will attempt to send a
    | reminder before marking it as failed.
    |
    */
    'max_attempts' => 3,

    /*
    |--------------------------------------------------------------------------
    | Queue Configuration
    |--------------------------------------------------------------------------
    |
    | Configure how reminders are processed through Laravel's queue system.
    | Set enabled to false to process reminders synchronously.
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
    | This value determines how often the scheduler checks for due reminders.
    | Value is in seconds. Common values: 15, 30, 60.
    |
    */
    'schedule_frequency' => 15,

    /*
    |--------------------------------------------------------------------------
    | Cleanup Configuration
    |--------------------------------------------------------------------------
    |
    | Automatically clean up old reminders to keep your database clean.
    |
    */
    'cleanup' => [
        'enabled' => env('REMINDER_CLEANUP_ENABLED', false),
        'after_days' => env('REMINDER_CLEANUP_AFTER_DAYS', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging Configuration
    |--------------------------------------------------------------------------
    |
    | Configure logging behavior for reminder processing.
    |
    */
    'logging' => [
        'enabled' => env('REMINDER_LOGGING_ENABLED', true),
        'channel' => env('REMINDER_LOG_CHANNEL', 'stack'),
    ],
];
