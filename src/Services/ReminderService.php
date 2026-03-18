<?php

declare(strict_types=1);

namespace Andydefer\LaravelReminder\Services;

use Andydefer\LaravelReminder\Contracts\ShouldRemind;
use Andydefer\LaravelReminder\Exceptions\InvalidNotificationException;
use Andydefer\LaravelReminder\Models\Reminder;
use Illuminate\Support\Facades\Log;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Notifications\Notification;
use Throwable;
use TypeError;

class ReminderService
{
    public function __construct(
        protected array $config = [],
        protected ?Dispatcher $events = null
    ) {}

    public function processPendingReminders(): array
    {
        $processed = 0;
        $failed = 0;

        $reminders = Reminder::with('remindable')->due()->get();

        foreach ($reminders as $reminder) {
            $result = $this->processReminder($reminder);

            if ($result) {
                $processed++;
            } else {
                $failed++;
            }
        }

        $this->dispatchEvent('processed', [
            'processed' => $processed,
            'failed' => $failed,
            'total' => $reminders->count(),
        ]);

        return [
            'processed' => $processed,
            'failed' => $failed,
            'total' => $reminders->count(),
        ];
    }

    public function processReminder(Reminder $reminder): bool
    {
        $this->dispatchEvent('processing', $reminder);

        try {
            /** @var \Illuminate\Database\Eloquent\Model $remindable */
            $remindable = $reminder->remindable;

            if (!$remindable instanceof ShouldRemind) {
                throw new \Exception(
                    sprintf(
                        'Model %s does not implement ShouldRemind interface',
                        get_class($remindable)
                    )
                );
            }

            if (!$this->isWithinTolerance($reminder, $remindable)) {
                $reminder->markAsFailed('Outside tolerance window');
                $this->dispatchEvent('outside_tolerance', $reminder);
                return false;
            }

            try {
                // Récupérer la notification directement depuis le modèle
                $notification = $remindable->toRemind($reminder);
            } catch (TypeError $e) {
                // Capturer les erreurs de type PHP (mauvais type de retour)
                $message = $this->extractTypeErrorMessage($e);
                throw InvalidNotificationException::create($message);
            }

            // Vérifier que c'est bien une notification Laravel
            if (!$notification instanceof Notification) {
                throw InvalidNotificationException::create($notification);
            }

            // Envoyer la notification via le système Laravel
            $remindable->notify($notification);

            $reminder->markAsSent();
            $this->dispatchEvent('sent', $reminder);

            Log::info('Reminder sent successfully', [
                'reminder_id' => $reminder->id,
                'remindable_type' => $reminder->remindable_type,
                'remindable_id' => $reminder->remindable_id,
                'notification_class' => get_class($notification),
            ]);

            return true;
        } catch (Throwable $e) {
            Log::error('Failed to process reminder', [
                'reminder_id' => $reminder->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $reminder->markAsFailed($e->getMessage());
            $this->dispatchEvent('failed', [$reminder, $e]);

            return false;
        }
    }

    /**
     * Extract a readable message from a TypeError
     */
    private function extractTypeErrorMessage(TypeError $e): string
    {
        $message = $e->getMessage();

        // Format: "Return value of ...::toRemind() must be an instance of ..., ... returned"
        if (preg_match('/must be an instance of [^,]+, (.+) returned/', $message, $matches)) {
            return sprintf('toRemind() must return an instance of Illuminate\Notifications\Notification, %s returned', $matches[1]);
        }

        return $message;
    }

    protected function isWithinTolerance(Reminder $reminder, ShouldRemind $remindable): bool
    {
        $tolerance = $remindable->getTolerance();
        return $tolerance->isWithinWindow($reminder->scheduled_at, now());
    }

    protected function dispatchEvent(string $event, mixed $payload): void
    {
        if ($this->events) {
            $this->events->dispatch("reminder.{$event}", $payload);
        }
    }

    public function setEventDispatcher(Dispatcher $events): self
    {
        $this->events = $events;
        return $this;
    }

    public function getConfig(): array
    {
        return $this->config;
    }

    public function getConfigValue(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }

    public function getMaxAttempts(): int
    {
        return $this->config['max_attempts'] ?? 3;
    }

    public function isQueueEnabled(): bool
    {
        return $this->config['queue']['enabled'] ?? true;
    }

    public function getQueueName(): string
    {
        return $this->config['queue']['name'] ?? 'default';
    }

    public function getQueueConnection(): string
    {
        return $this->config['queue']['connection'] ?? config('queue.default', 'sync');
    }
}
