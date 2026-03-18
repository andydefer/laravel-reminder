<?php

declare(strict_types=1);

namespace Andydefer\LaravelReminder\Traits;

use Andydefer\LaravelReminder\Enums\ReminderStatus;
use Andydefer\LaravelReminder\Models\Reminder;
use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use InvalidArgumentException;

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
     * @param array|null $channels Custom channels for this reminder
     * @return Reminder
     *
     * @throws InvalidArgumentException
     */
    public function scheduleReminder(
        DateTimeInterface|string $scheduledAt,
        array $metadata = [],
        ?array $channels = []
    ): Reminder {
        $scheduledAt = $this->parseScheduledAt($scheduledAt);

        if ($scheduledAt->isPast()) {
            throw new InvalidArgumentException('Cannot schedule a reminder in the past');
        }

        return $this->reminders()->create([
            'scheduled_at' => $scheduledAt,
            'metadata' => $metadata,
            'channels' => $channels ?? [],
            'status' => ReminderStatus::PENDING,
            'attempts' => 0,
        ]);
    }

    /**
     * Schedule multiple reminders at once.
     *
     * @param array $scheduledTimes Array of dates or associative array [date => channels]
     * @param array<string, mixed> $metadata Additional data for all reminders
     * @param array|null $globalChannels Default channels for reminders without specific channels
     * @return array<int, Reminder>
     *
     * @throws InvalidArgumentException
     */
    public function scheduleMultipleReminders(array $scheduledTimes, array $metadata = [], ?array $globalChannels = []): array
    {
        if (empty($scheduledTimes)) {
            throw new InvalidArgumentException('Scheduled times array cannot be empty');
        }

        $reminders = [];

        foreach ($scheduledTimes as $key => $value) {
            // Cas 1 : la valeur est un tableau → format [date => channels]
            if (is_array($value)) {
                $scheduledAt = $key;
                $channels = $value;
            }
            // Cas 2 : la clé est un entier et la valeur n'est pas un tableau → format indexé [date, date]
            elseif (is_int($key)) {
                $scheduledAt = $value;
                $channels = $globalChannels;
            }
            // Cas 3 : la clé n'est pas un entier (donc c'est une date) et la valeur n'est pas un tableau
            // C'est le cas mixte où on a une date sans channels spécifiques mais avec une clé associative
            else {
                $scheduledAt = $key;
                $channels = $globalChannels;
            }

            $reminders[] = $this->scheduleReminder($scheduledAt, $metadata, $channels);
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
     *
     * @param DateTimeInterface|string $scheduledAt
     * @return Carbon
     * @throws InvalidArgumentException
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
            throw new InvalidArgumentException(
                'Invalid date format for scheduled_at: ' . $scheduledAt
            );
        }
    }
}
