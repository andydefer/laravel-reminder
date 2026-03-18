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
    ];

    public function remindable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Retourne les channels à utiliser pour ce reminder
     */
    public function channels(): array
    {
        return $this->channels ?? [];
    }

    /**
     * Mark the reminder as sent.
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
     */
    public function cancel(): self
    {
        $this->update(['status' => ReminderStatus::CANCELLED]);

        return $this;
    }

    /**
     * Check if the reminder is still pending.
     */
    public function isPending(): bool
    {
        return $this->status === ReminderStatus::PENDING;
    }

    /**
     * Check if the reminder was sent.
     */
    public function wasSent(): bool
    {
        return $this->status === ReminderStatus::SENT;
    }

    /**
     * Check if the reminder has failed.
     */
    public function hasFailed(): bool
    {
        return $this->status === ReminderStatus::FAILED;
    }

    /**
     * Indique si des channels personnalisés ont été définis
     */
    public function getHasCustomChannelsAttribute(): bool
    {
        $channels = $this->channels();
        return !empty($channels);
    }

    /**
     * Retourne les channels à utiliser pour ce reminder (custom si défini, sinon fallback)
     *
     * @param array $fallbackChannels Channels à utiliser si aucun custom défini
     * @return array
     */
    public function channelsForSending(array $fallbackChannels = ['mail']): array
    {
        return $this->has_custom_channels
            ? $this->channels()
            : $fallbackChannels;
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', ReminderStatus::PENDING->value);
    }

    public function scopeDue($query)
    {
        return $query->where('status', ReminderStatus::PENDING->value)
            ->where('scheduled_at', '<=', now())
            ->where('attempts', '<', config('reminder.max_attempts', 3));
    }

    public function scopeWithinTolerance($query, int $toleranceMinutes)
    {
        return $query->whereBetween('scheduled_at', [
            now()->subMinutes($toleranceMinutes),
            now()->addMinutes($toleranceMinutes)
        ]);
    }
}
