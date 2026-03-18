<?php

declare(strict_types=1);

namespace Andydefer\LaravelReminder\Tests\Unit\Traits;

use Andydefer\LaravelReminder\Enums\ReminderStatus;
use Andydefer\LaravelReminder\Models\Reminder;
use Andydefer\LaravelReminder\Tests\TestCase;
use Carbon\Carbon;
use InvalidArgumentException;

/**
 * Unit tests for the Remindable trait.
 *
 * This test suite verifies the core functionality of the Remindable trait:
 * - Scheduling single and multiple reminders
 * - Canceling reminders
 * - Retrieving pending reminders
 * - Validation of scheduled dates
 *
 * @package Andydefer\LaravelReminder\Tests\Unit\Traits
 */
class RemindableTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Freeze time to have consistent tests
        Carbon::setTestNow(Carbon::parse('2025-01-01 10:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * Test that a reminder can be scheduled with a Carbon instance.
     *
     * @return void
     */
    public function test_can_schedule_reminder_with_carbon(): void
    {
        // Arrange
        $model = $this->createTestRemindableModel();
        $scheduledAt = Carbon::tomorrow()->setTime(10, 0);
        $metadata = ['type' => 'test', 'priority' => 'high'];

        // Act
        $reminder = $model->scheduleReminder($scheduledAt, $metadata);

        // Assert
        $this->assertInstanceOf(Reminder::class, $reminder);
        $this->assertEquals($scheduledAt->toDateTimeString(), $reminder->scheduled_at->toDateTimeString());
        $this->assertEquals($metadata, $reminder->metadata);
        $this->assertEquals(ReminderStatus::PENDING, $reminder->status);
        $this->assertEquals(0, $reminder->attempts);
        $this->assertNull($reminder->sent_at);
    }

    /**
     * Test that a reminder can be scheduled with a date string.
     *
     * @return void
     */
    public function test_can_schedule_reminder_with_date_string(): void
    {
        // Arrange
        $model = $this->createTestRemindableModel();
        $scheduledAt = '2025-12-25 09:00:00';

        // Act
        $reminder = $model->scheduleReminder($scheduledAt);

        // Assert
        $this->assertInstanceOf(Reminder::class, $reminder);
        $this->assertEquals(Carbon::parse($scheduledAt)->toDateTimeString(), $reminder->scheduled_at->toDateTimeString());
    }

    /**
     * Test that scheduling a reminder in the past throws an exception.
     *
     * @return void
     */
    public function test_throws_exception_when_scheduling_past_date(): void
    {
        // Arrange
        $model = $this->createTestRemindableModel();
        $pastDate = Carbon::yesterday();

        // Assert
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot schedule a reminder in the past');

        // Act
        $model->scheduleReminder($pastDate);
    }

    /**
     * Test that scheduling with an invalid date string throws an exception.
     *
     * @return void
     */
    public function test_throws_exception_with_invalid_date_string(): void
    {
        // Arrange
        $model = $this->createTestRemindableModel();
        $invalidDate = 'not-a-date';

        // Assert
        $this->expectException(InvalidArgumentException::class);

        // Act
        $model->scheduleReminder($invalidDate);
    }

    /**
     * Test that multiple reminders can be scheduled at once.
     *
     * @return void
     */
    public function test_can_schedule_multiple_reminders(): void
    {
        // Arrange
        $model = $this->createTestRemindableModel();
        $dates = [
            Carbon::tomorrow()->setTime(9, 0),
            Carbon::tomorrow()->setTime(12, 0),
            Carbon::tomorrow()->setTime(18, 0),
        ];

        // Act
        $reminders = $model->scheduleMultipleReminders($dates);

        // Assert
        $this->assertCount(3, $reminders);
        foreach ($reminders as $index => $reminder) {
            $this->assertEquals($dates[$index]->toDateTimeString(), $reminder->scheduled_at->toDateTimeString());
        }
    }

    /**
     * Test that all pending reminders can be canceled.
     *
     * @return void
     */
    public function test_can_cancel_all_pending_reminders(): void
    {
        // Arrange
        $model = $this->createTestRemindableModel();

        $reminder1 = $model->scheduleReminder(Carbon::tomorrow());
        $reminder2 = $model->scheduleReminder(Carbon::tomorrow()->addDay());
        $sentReminder = $model->scheduleReminder(Carbon::tomorrow()->addDays(2));
        $sentReminder->markAsSent();

        // Act
        $cancelledCount = $model->cancelReminders();

        // Assert
        $this->assertEquals(2, $cancelledCount);
        $this->assertEquals(ReminderStatus::CANCELLED, $reminder1->fresh()->status);
        $this->assertEquals(ReminderStatus::CANCELLED, $reminder2->fresh()->status);
        $this->assertEquals(ReminderStatus::SENT, $sentReminder->fresh()->status);
    }

    /**
     * Test that pending reminders can be retrieved.
     *
     * @return void
     */
    public function test_can_get_pending_reminders(): void
    {
        // Arrange
        $model = $this->createTestRemindableModel();

        $model->scheduleReminder(Carbon::tomorrow());
        $model->scheduleReminder(Carbon::tomorrow()->addDay());
        $sentReminder = $model->scheduleReminder(Carbon::tomorrow()->addDays(2));
        $sentReminder->markAsSent();

        // Act
        $pendingReminders = $model->pendingReminders();

        // Assert
        $this->assertCount(2, $pendingReminders);
    }

    /**
     * Test that hasPendingReminders returns correct boolean.
     *
     * @return void
     */
    public function test_has_pending_reminders_returns_correct_boolean(): void
    {
        // Arrange
        $model = $this->createTestRemindableModel();

        // Assert - initially no pending reminders
        $this->assertFalse($model->hasPendingReminders());

        // Act
        $model->scheduleReminder(Carbon::tomorrow());

        // Assert - now has pending reminders
        $this->assertTrue($model->hasPendingReminders());
    }

    /**
     * Test that nextReminder returns the closest upcoming reminder.
     *
     * @return void
     */
    public function test_next_reminder_returns_closest_upcoming(): void
    {
        // Arrange
        $model = $this->createTestRemindableModel();

        $later = Carbon::tomorrow()->setTime(18, 0);
        $sooner = Carbon::tomorrow()->setTime(9, 0);

        $model->scheduleReminder($later);
        $model->scheduleReminder($sooner);

        // Act
        $nextReminder = $model->nextReminder();

        // Assert
        $this->assertNotNull($nextReminder);
        $this->assertEquals($sooner->toDateTimeString(), $nextReminder->scheduled_at->toDateTimeString());
    }
}
