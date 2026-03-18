<?php

declare(strict_types=1);

namespace Andydefer\LaravelReminder\Tests\Fixtures;

use Andydefer\LaravelReminder\Models\Reminder;
use Illuminate\Notifications\Notification;

class TestNotification extends Notification
{
    public function __construct(
        protected TestRemindableModel $model,
        protected Reminder $reminder
    ) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toDatabase($notifiable): array
    {
        return [
            'model_id' => $this->model->id,
            'model_name' => $this->model->name,
            'reminder_id' => $this->reminder->id,
            'scheduled_at' => $this->reminder->scheduled_at->toDateTimeString(),
        ];
    }

    public function toArray($notifiable): array
    {
        return $this->toDatabase($notifiable);
    }
}
