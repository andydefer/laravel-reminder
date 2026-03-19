<?php

declare(strict_types=1);

namespace Andydefer\LaravelReminder\Models;

use Andydefer\LaravelReminder\Casts\ChannelsCast;
use Andydefer\LaravelReminder\Enums\ReminderStatus;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property string $remindable_type
 * @property int $remindable_id
 * @property Carbon $scheduled_at
 * @property Carbon|null $sent_at
 * @property ReminderStatus $status
 * @property array|null $metadata
 * @property array $channels
 * @property int $attempts
 * @property Carbon|null $last_attempt_at
 * @property string|null $error_message
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder|static pending()
 * @method static \Illuminate\Database\Eloquent\Builder|static due()
 * @method static \Illuminate\Database\Eloquent\Builder|static withinTolerance(int $toleranceMinutes)
 */
class Reminder extends Model
{
    protected $table = 'reminders';

    protected $fillable = [
        'remindable_type',
        'remindable_id',
        'scheduled_at',
        'sent_at',
        'status',
        'metadata',
        'channels',
        'attempts',
        'last_attempt_at',
        'error_message',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'sent_at' => 'datetime',
        'last_attempt_at' => 'datetime',
        'metadata' => 'array',
        'channels' => ChannelsCast::class,
        'status' => ReminderStatus::class,
        'attempts' => 'integer',
    ];

    protected $attributes = [
        'attempts' => 0,
        'status' => ReminderStatus::PENDING,
        // FIX: Add default value at model level instead of database
        // This ensures new records have an empty array for channels
        'channels' => '[]', // JSON string representation of empty array
    ];

    /**
     * Get the parent remindable model.
     */
    public function remindable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the channels for this reminder.
     *
     * @return array
     */
    public function channels(): array
    {
        return $this->channels ?? [];
    }

    /**
     * Mark the reminder as sent.
     *
     * @return $this
     */
    public function markAsSent(): self
    {
        $this->update([
            'status' => ReminderStatus::SENT,
            'sent_at' => now(),
            'last_attempt_at' => now(),
        ]);

        return $this;
    }

    /**
     * Mark the reminder as failed.
     *
     * @param string $error
     * @return $this
     */
    public function markAsFailed(string $error): self
    {
        $maxAttempts = config('reminder.max_attempts', 3);
        $newStatus = $this->attempts + 1 >= $maxAttempts
            ? ReminderStatus::FAILED
            : ReminderStatus::PENDING;

        $this->update([
            'status' => $newStatus,
            'attempts' => $this->attempts + 1,
            'last_attempt_at' => now(),
            'error_message' => $error,
        ]);

        return $this;
    }

    /**
     * Cancel the reminder.
     *
     * @return $this
     */
    public function cancel(): self
    {
        $this->update(['status' => ReminderStatus::CANCELLED]);

        return $this;
    }

    /**
     * Check if the reminder is still pending.
     *
     * @return bool
     */
    public function isPending(): bool
    {
        return $this->status === ReminderStatus::PENDING;
    }

    /**
     * Check if the reminder was sent.
     *
     * @return bool
     */
    public function wasSent(): bool
    {
        return $this->status === ReminderStatus::SENT;
    }

    /**
     * Check if the reminder has failed.
     *
     * @return bool
     */
    public function hasFailed(): bool
    {
        return $this->status === ReminderStatus::FAILED;
    }

    /**
     * Check if custom channels were set.
     *
     * @return bool
     */
    public function getHasCustomChannelsAttribute(): bool
    {
        $channels = $this->channels();
        return !empty($channels);
    }

    /**
     * Get channels to use for sending (custom if set, otherwise fallback).
     *
     * @param array $fallbackChannels
     * @return array
     */
    public function channelsForSending(array $fallbackChannels = ['mail']): array
    {
        return $this->has_custom_channels
            ? $this->channels()
            : $fallbackChannels;
    }

    /**
     * Scope a query to only include pending reminders.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopePending($query)
    {
        return $query->where('status', ReminderStatus::PENDING->value);
    }

    /**
     * Scope a query to only include due reminders.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeDue($query)
    {
        return $query->where('status', ReminderStatus::PENDING->value)
            ->where('scheduled_at', '<=', now())
            ->where('attempts', '<', config('reminder.max_attempts', 3));
    }

    /**
     * Scope a query to only include reminders within a tolerance window.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $toleranceMinutes
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWithinTolerance($query, int $toleranceMinutes)
    {
        return $query->whereBetween('scheduled_at', [
            now()->subMinutes($toleranceMinutes),
            now()->addMinutes($toleranceMinutes)
        ]);
    }
}
