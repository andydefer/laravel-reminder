<?php

declare(strict_types=1);

namespace Andydefer\LaravelReminder\Enums;

enum ToleranceUnit: string
{
    case YEAR = 'year';
    case MONTH = 'month';
    case WEEK = 'week';
    case DAY = 'day';
    case HOUR = 'hour';
    case MINUTE = 'minute';

    public function toMinutes(): int
    {
        return match ($this) {
            self::YEAR => 525600,
            self::MONTH => 43800,
            self::WEEK => 10080,
            self::DAY => 1440,
            self::HOUR => 60,
            self::MINUTE => 1,
        };
    }

    public function toSeconds(): int
    {
        return $this->toMinutes() * 60;
    }

    public function label(): string
    {
        return match ($this) {
            self::YEAR => 'Year',
            self::MONTH => 'Month',
            self::WEEK => 'Week',
            self::DAY => 'Day',
            self::HOUR => 'Hour',
            self::MINUTE => 'Minute',
        };
    }
}
