<?php

declare(strict_types=1);

namespace Andydefer\LaravelReminder\Tests\Fixtures;

use Andydefer\LaravelReminder\Contracts\ShouldRemind;
use Andydefer\LaravelReminder\Enums\ToleranceUnit;
use Andydefer\LaravelReminder\Models\Reminder;
use Andydefer\LaravelReminder\Traits\Remindable;
use Andydefer\LaravelReminder\ValueObjects\Tolerance;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Illuminate\Notifications\Notification;

/**
 * Test remindable model for package testing.
 *
 * This fixture model implements the ShouldRemind contract to simulate
 * a remindable entity in the testing environment. It uses the test_remindable_models
 * table created by the package's test migration.
 *
 * @property int $id
 * @property string $name
 * @property string|null $email
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 *
 * @package Andydefer\LaravelReminder\Tests\Fixtures
 */
class TestRemindableModel extends Model implements ShouldRemind
{
    use Remindable, Notifiable;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'test_remindable_models';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the notification for this reminder.
     *
     * @param Reminder $reminder The reminder instance being processed
     * @return Notification
     */
    public function toRemind(Reminder $reminder): Notification
    {
        return new TestNotification($this, $reminder);
    }

    /**
     * Get the tolerance window for this remindable.
     *
     * @return Tolerance
     */
    public function getTolerance(): Tolerance
    {
        return new Tolerance(30, ToleranceUnit::MINUTE);
    }

    /**
     * Get the name of the database connection for notifications.
     */
    public function receivesBroadcastNotificationsOn(): string
    {
        return 'test-notifications';
    }

    /**
     * Get the channels of the last reminder (helper for testing).
     *
     * @return array|null
     */
    public function getLastReminderChannels(): ?array
    {
        $lastReminder = $this->reminders()->latest()->first();
        return $lastReminder ? $lastReminder->channels() : null;
    }

    /**
     * Check if the last reminder has custom channels (helper for testing).
     *
     * @return bool
     */
    public function lastReminderHasCustomChannels(): bool
    {
        $lastReminder = $this->reminders()->latest()->first();
        return $lastReminder ? $lastReminder->has_custom_channels : false;
    }

    /**
     * Get all reminders with their channels (helper for testing).
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getRemindersWithChannels()
    {
        return $this->reminders()->get()->map(function ($reminder) {
            return [
                'id' => $reminder->id,
                'scheduled_at' => $reminder->scheduled_at,
                'channels' => $reminder->channels(),
                'has_custom_channels' => $reminder->has_custom_channels,
            ];
        });
    }
}
