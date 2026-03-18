<?php

declare(strict_types=1);

namespace Andydefer\LaravelReminder\Contracts;

use Andydefer\LaravelReminder\Models\Reminder;
use Andydefer\LaravelReminder\ValueObjects\Tolerance;
use Illuminate\Notifications\Notification;

interface ShouldRemind
{
    /**
     * Get the notification to send for this reminder.
     *
     * @param Reminder $reminder The reminder instance being processed
     * @return Notification The notification to send
     */
    public function toRemind(Reminder $reminder): Notification;

    /**
     * Get the tolerance window for this reminder.
     *
     * @return Tolerance
     */
    public function getTolerance(): Tolerance;
}
