<?php

declare(strict_types=1);

namespace Andydefer\LaravelReminder\Contracts;

use Andydefer\LaravelReminder\Models\Reminder;
use Andydefer\LaravelReminder\ValueObjects\Tolerance;

interface ShouldRemind
{
    /**
     * Get the notification data to send for this reminder.
     *
     * @param Reminder $reminder The reminder instance being processed
     * @return array{
     *     title: string,
     *     body: string,
     *     type?: string,
     *     data?: array<string, mixed>,
     *     imageUrl?: string|null
     * }
     */
    public function toRemind(Reminder $reminder): array;

    /**
     * Get the tolerance window for this reminder.
     *
     * @return Tolerance
     */
    public function getTolerance(): Tolerance;
}
