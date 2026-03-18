<?php

declare(strict_types=1);

namespace Andydefer\LaravelReminder\Enums;

enum ReminderStatus: string
{
    case PENDING = 'pending';
    case SENT = 'sent';
    case FAILED = 'failed';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::SENT => 'Sent',
            self::FAILED => 'Failed',
            self::CANCELLED => 'Cancelled',
        };
    }

    public function isPending(): bool
    {
        return $this === self::PENDING;
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::SENT, self::FAILED, self::CANCELLED], true);
    }
}
