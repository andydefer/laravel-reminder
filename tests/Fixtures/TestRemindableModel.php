<?php

declare(strict_types=1);

namespace Andydefer\LaravelReminder\Tests\Fixtures;

use Andydefer\LaravelReminder\Contracts\ShouldRemind;
use Andydefer\LaravelReminder\Enums\ToleranceUnit;
use Andydefer\LaravelReminder\Models\Reminder;
use Andydefer\LaravelReminder\Traits\Remindable;
use Andydefer\LaravelReminder\ValueObjects\Tolerance;
use Illuminate\Database\Eloquent\Model;

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
    use Remindable;

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
     * Get the notification data for this reminder.
     *
     * @param Reminder $reminder The reminder instance being processed
     * @return array{title: string, body: string, type?: string, data?: array, imageUrl?: string|null}
     */
    public function toRemind(Reminder $reminder): array
    {
        return [
            'title' => 'Test Reminder: ' . $this->name,
            'body' => 'This is a test reminder scheduled at ' . $reminder->scheduled_at->format('Y-m-d H:i:s'),
            'type' => 'test_notification',
            'data' => $reminder->metadata ?? [],
            'imageUrl' => null,
        ];
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
}
