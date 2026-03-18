<?php

declare(strict_types=1);

namespace Andydefer\LaravelReminder\Tests\Fixtures;

use Andydefer\LaravelReminder\Models\Reminder;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class TestNotification extends Notification
{
    public function __construct(
        public TestRemindableModel $model,
        public Reminder $reminder
    ) {}

    public function via($notifiable): array
    {

        return $this->reminder->channelsForSending(['database']);
    }

    /*   public function via($notifiable): array
    {
        // Pour les tests, on utilise toujours database pour que ce soit simple
        return ['database'];
    } */


    public function toDatabase($notifiable): array
    {
        return [
            'model_id' => $this->model->id,
            'model_name' => $this->model->name,
            'reminder_id' => $this->reminder->id,
            'scheduled_at' => $this->reminder->scheduled_at->toDateTimeString(),
            'channels' => $this->reminder->channels(),
            'has_custom_channels' => $this->reminder->has_custom_channels,
        ];
    }

    public function toArray($notifiable): array
    {
        return $this->toDatabase($notifiable);
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Test Reminder')
            ->line('This is a test reminder')
            ->line('Scheduled at: ' . $this->reminder->scheduled_at->toDateTimeString())
            ->line('Channels: ' . implode(', ', $this->reminder->channels()));
    }
}
