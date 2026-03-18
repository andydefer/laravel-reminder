<?php

declare(strict_types=1);

namespace Andydefer\LaravelReminder\Traits;

use Andydefer\LaravelReminder\Enums\ReminderStatus;
use Andydefer\LaravelReminder\Models\Reminder;
use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait Remindable
{
    /**
     * Get all reminders for this model.
     */
    public function reminders(): MorphMany
    {
        return $this->morphMany(Reminder::class, 'remindable');
    }

    /**
     * Schedule a new reminder.
     *
     * @param DateTimeInterface|string $scheduledAt When the reminder should be sent
     * @param array<string, mixed> $metadata Additional data to store with the reminder
     * @return Reminder
     *
     * @throws \InvalidArgumentException
     */
    public function scheduleReminder(
        DateTimeInterface|string $scheduledAt,
        array $metadata = []
    ): Reminder {
        $scheduledAt = $this->parseScheduledAt($scheduledAt);

        if ($scheduledAt->isPast()) {
            throw new \InvalidArgumentException('Cannot schedule a reminder in the past');
        }

        return $this->reminders()->create([
            'scheduled_at' => $scheduledAt,
            'metadata' => $metadata,
            'status' => ReminderStatus::PENDING,
            'attempts' => 0,
        ]);
    }

    /**
     * Schedule multiple reminders at once.
     *
     * @param array<DateTimeInterface|string> $scheduledTimes
     * @param array<string, mixed> $metadata
     * @return array<int, Reminder>
     */
    public function scheduleMultipleReminders(array $scheduledTimes, array $metadata = []): array
    {
        $reminders = [];

        foreach ($scheduledTimes as $scheduledAt) {
            $reminders[] = $this->scheduleReminder($scheduledAt, $metadata);
        }

        return $reminders;
    }

    /**
     * Cancel all pending reminders for this model.
     */
    public function cancelReminders(): int
    {
        return $this->reminders()
            ->where('status', ReminderStatus::PENDING->value)
            ->update(['status' => ReminderStatus::CANCELLED]);
    }

    /**
     * Get all pending reminders.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Reminder>
     */
    public function pendingReminders()
    {
        return $this->reminders()->pending()->get();
    }

    /**
     * Check if there are any pending reminders.
     */
    public function hasPendingReminders(): bool
    {
        return $this->reminders()->pending()->exists();
    }

    /**
     * Get the next upcoming reminder.
     */
    public function nextReminder(): ?Reminder
    {
        return $this->reminders()
            ->pending()
            ->orderBy('scheduled_at')
            ->first();
    }

    /**
     * Parse the scheduled at value.
     */
    private function parseScheduledAt(DateTimeInterface|string $scheduledAt): Carbon
    {
        if ($scheduledAt instanceof DateTimeInterface) {
            return $scheduledAt instanceof Carbon
                ? $scheduledAt
                : Carbon::instance($scheduledAt);
        }

        try {
            return Carbon::parse($scheduledAt);
        } catch (\Exception $e) {
            throw new \InvalidArgumentException(
                'Invalid date format for scheduled_at: ' . $scheduledAt
            );
        }
    }
}
