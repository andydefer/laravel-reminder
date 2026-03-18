<?php

declare(strict_types=1);

namespace Andydefer\LaravelReminder\Models;

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
        'attempts',
        'last_attempt_at',
        'error_message',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'sent_at' => 'datetime',
        'last_attempt_at' => 'datetime',
        'metadata' => 'array',
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
     * Scope a query to only include pending reminders.
     */
    public function scopePending($query)
    {
        return $query->where('status', ReminderStatus::PENDING->value);
    }

    /**
     * Scope a query to only include due reminders.
     */
    public function scopeDue($query)
    {
        return $query->where('status', ReminderStatus::PENDING->value)
            ->where('scheduled_at', '<=', now())
            ->where('attempts', '<', 3);
    }

    /**
     * Scope a query to include reminders within tolerance window.
     */
    public function scopeWithinTolerance($query, int $toleranceMinutes)
    {
        return $query->whereBetween('scheduled_at', [
            now()->subMinutes($toleranceMinutes),
            now()->addMinutes($toleranceMinutes)
        ]);
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
}
