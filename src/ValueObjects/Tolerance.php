<?php

declare(strict_types=1);

namespace Andydefer\LaravelReminder\ValueObjects;

use Andydefer\LaravelReminder\Enums\ToleranceUnit;

class Tolerance
{
    public function __construct(
        public readonly int $value,
        public readonly ToleranceUnit $unit
    ) {
        if ($value < 0) {
            throw new \InvalidArgumentException('Tolerance value cannot be negative');
        }
    }

    public function toMinutes(): int
    {
        return $this->value * $this->unit->toMinutes();
    }

    public function toSeconds(): int
    {
        return $this->value * $this->unit->toSeconds();
    }

    public function isWithinWindow(\DateTimeInterface $scheduledAt, \DateTimeInterface $now): bool
    {
        $diffInMinutes = abs($now->getTimestamp() - $scheduledAt->getTimestamp()) / 60;
        return $diffInMinutes <= $this->toMinutes();
    }

    public function __toString(): string
    {
        return $this->value . ' ' . $this->unit->label() . ($this->value > 1 ? 's' : '');
    }
}
