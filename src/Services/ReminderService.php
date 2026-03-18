<?php

declare(strict_types=1);

namespace Andydefer\LaravelReminder\Services;

use Andydefer\LaravelReminder\Contracts\ShouldRemind;
use Andydefer\LaravelReminder\Models\Reminder;
use Illuminate\Support\Facades\Log;
use Illuminate\Contracts\Events\Dispatcher;
use Throwable;

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
        $this->dispatchEvent('processing', $reminder); // ✅ corrigé

        try {
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

            $notificationData = $remindable->toRemind($reminder);
            $this->sendNotification($reminder, $notificationData);

            $reminder->markAsSent();
            $this->dispatchEvent('sent', $reminder); // ✅ corrigé

            Log::info('Reminder sent successfully', [
                'reminder_id' => $reminder->id,
                'remindable_type' => $reminder->remindable_type,
                'remindable_id' => $reminder->remindable_id,
            ]);

            return true;
        } catch (Throwable $e) {
            Log::error('Failed to process reminder', [
                'reminder_id' => $reminder->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $reminder->markAsFailed($e->getMessage());
            $this->dispatchEvent('failed', [$reminder, $e]); // ✅ corrigé

            return false;
        }
    }

    protected function isWithinTolerance(Reminder $reminder, ShouldRemind $remindable): bool
    {
        $tolerance = $remindable->getTolerance();
        return $tolerance->isWithinWindow($reminder->scheduled_at, now());
    }

    protected function sendNotification(Reminder $reminder, array $data): void
    {
        if (!isset($data['title']) || !isset($data['body'])) {
            throw new \InvalidArgumentException('Notification data must contain title and body');
        }

        Log::debug('Preparing to send reminder notification', [
            'reminder_id' => $reminder->id,
            'notification_data' => $data,
        ]);

        $this->dispatchEvent('sending', [$reminder, $data]); // ✅ corrigé
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
