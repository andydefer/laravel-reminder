<?php

declare(strict_types=1);

namespace Andydefer\LaravelReminder\Services;

use Andydefer\LaravelReminder\Contracts\ShouldRemind;
use Andydefer\LaravelReminder\Models\Reminder;
use Carbon\Carbon;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Notifications\Notification;
use Throwable;

class ReminderService
{
    public function __construct(
        protected array $config,
        protected Dispatcher $events
    ) {}

    /**
     * Process a single reminder.
     */
    public function processReminder(Reminder $reminder): bool
    {
        $this->events->dispatch('reminder.processing', $reminder);

        try {
            // Vérifier si le remindable implémente ShouldRemind
            /** @var \Illuminate\Database\Eloquent\Model $remindable */
            $remindable = $reminder->remindable;

            if (!$remindable instanceof ShouldRemind) {
                throw new \RuntimeException(
                    get_class($remindable) . ' does not implement ShouldRemind interface'
                );
            }

            // Vérifier la fenêtre de tolérance
            $tolerance = $remindable->getTolerance();
            $now = Carbon::now();

            if (!$tolerance->isWithinWindow($reminder->scheduled_at, $now)) {
                $reminder->markAsFailed('Outside tolerance window');
                $this->events->dispatch('reminder.outside_tolerance', $reminder);
                return false;
            }

            // Obtenir la notification
            $notification = $remindable->toRemind($reminder);

            if (!$notification instanceof Notification) {
                throw new \RuntimeException(
                    'toRemind() must return an instance of Illuminate\Notifications\Notification'
                );
            }

            // Envoyer la notification
            $remindable->notify($notification);

            // Marquer comme envoyé
            $reminder->markAsSent();
            $this->events->dispatch('reminder.sent', $reminder);

            return true;
        } catch (Throwable $e) {
            // Gérer l'échec
            $reminder->markAsFailed($e->getMessage());
            $this->events->dispatch('reminder.failed', [$reminder, $e]);

            return false;
        }
    }

    /**
     * Process all pending reminders.
     */
    public function processPendingReminders(): array
    {
        $reminders = Reminder::due()->get();

        $results = [
            'total' => $reminders->count(),
            'processed' => 0,
            'failed' => 0,
        ];

        foreach ($reminders as $reminder) {
            $success = $this->processReminder($reminder);

            if ($success) {
                $results['processed']++;
            } else {
                $results['failed']++;
            }
        }

        $this->events->dispatch('reminder.processed', $results);

        return $results;
    }

    /**
     * Get the service configuration.
     */
    public function getConfig(): array
    {
        return $this->config;
    }
}
