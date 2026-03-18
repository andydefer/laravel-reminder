<?php

declare(strict_types=1);

namespace Andydefer\LaravelReminder\Exceptions;

class InvalidNotificationException extends \InvalidArgumentException
{
    public static function create(mixed $actual): self
    {
        if (is_string($actual)) {
            return new self($actual);
        }

        $type = is_object($actual) ? get_class($actual) : gettype($actual);

        return new self(
            sprintf(
                'toRemind() must return an instance of Illuminate\Notifications\Notification. Got: %s',
                $type
            )
        );
    }
}
