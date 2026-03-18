<?php

declare(strict_types=1);

namespace Andydefer\LaravelReminder\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static array processPendingReminders()
 * @method static bool processReminder(\Andydefer\LaravelReminder\Models\Reminder $reminder)
 * @method static \Andydefer\LaravelReminder\Services\ReminderService setEventDispatcher(\Illuminate\Contracts\Events\Dispatcher $events)
 *
 * @see \Andydefer\LaravelReminder\Services\ReminderService
 */
class Reminder extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Andydefer\LaravelReminder\Services\ReminderService::class;
    }
}
