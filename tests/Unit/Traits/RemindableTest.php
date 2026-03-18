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

    /**
     * Test that a reminder can be scheduled with channels.
     *
     * @return void
     */
    public function test_can_schedule_reminder_with_channels(): void
    {
        // Arrange
        $model = $this->createTestRemindableModel();
        $scheduledAt = Carbon::tomorrow()->setTime(10, 0);
        $metadata = ['type' => 'test'];
        $channels = ['mail', 'sms', 'database'];

        // Act
        $reminder = $model->scheduleReminder($scheduledAt, $metadata, $channels);

        // Assert
        $this->assertInstanceOf(Reminder::class, $reminder);
        $this->assertEquals($channels, $reminder->channels);
        $this->assertTrue($reminder->has_custom_channels);
        $this->assertEquals($scheduledAt->toDateTimeString(), $reminder->scheduled_at->toDateTimeString());
        $this->assertEquals($metadata, $reminder->metadata);
    }

    /**
     * Test that a reminder can be scheduled without channels.
     *
     * @return void
     */
    public function test_can_schedule_reminder_without_channels(): void
    {
        // Arrange
        $model = $this->createTestRemindableModel();
        $scheduledAt = Carbon::tomorrow()->setTime(10, 0);

        // Act
        $reminder = $model->scheduleReminder($scheduledAt);

        // Assert
        $this->assertInstanceOf(Reminder::class, $reminder);
        $this->assertIsArray($reminder->channels);
        $this->assertEmpty($reminder->channels);
        $this->assertFalse($reminder->has_custom_channels);
    }

    /**
     * Test that multiple reminders can be scheduled with different channels.
     *
     * @return void
     */
    public function test_can_schedule_multiple_reminders_with_different_channels(): void
    {
        // Arrange
        $model = $this->createTestRemindableModel();

        // Convertir les Carbon en strings pour éviter les problèmes de clés
        $scheduledTimes = [
            Carbon::tomorrow()->setTime(9, 0)->format('Y-m-d H:i:s') => ['mail'],
            Carbon::tomorrow()->setTime(12, 0)->format('Y-m-d H:i:s') => ['mail', 'sms'],
            Carbon::tomorrow()->setTime(18, 0)->format('Y-m-d H:i:s') => ['sms', 'push', 'slack'],
        ];

        // Act
        $reminders = $model->scheduleMultipleReminders($scheduledTimes, ['priority' => 'high']);

        // Assert
        $this->assertCount(3, $reminders);

        $this->assertEquals(['mail'], $reminders[0]->channels());
        $this->assertEquals(['mail', 'sms'], $reminders[1]->channels());
        $this->assertEquals(['sms', 'push', 'slack'], $reminders[2]->channels());

        foreach ($reminders as $reminder) {
            $this->assertTrue($reminder->has_custom_channels);
            $this->assertEquals(['priority' => 'high'], $reminder->metadata);
        }
    }


    /**
     * Test that multiple reminders can be scheduled with global channels.
     *
     * @return void
     */
    public function test_can_schedule_multiple_reminders_with_global_channels(): void
    {
        // Arrange
        $model = $this->createTestRemindableModel();

        $dates = [
            Carbon::tomorrow()->setTime(9, 0),
            Carbon::tomorrow()->setTime(12, 0),
            Carbon::tomorrow()->setTime(18, 0),
        ];

        $globalChannels = ['mail', 'database'];

        // Act
        $reminders = $model->scheduleMultipleReminders($dates, ['priority' => 'high'], $globalChannels);

        // Assert
        $this->assertCount(3, $reminders);

        foreach ($reminders as $reminder) {
            $this->assertEquals($globalChannels, $reminder->channels);
            $this->assertTrue($reminder->has_custom_channels);
            $this->assertEquals(['priority' => 'high'], $reminder->metadata);
        }
    }

    /**
     * Test that multiple reminders can be scheduled with mixed format (dates with and without channels).
     *
     * @return void
     */

    public function test_schedule_multiple_reminders_mixed_format_with_channels(): void
    {
        // Arrange
        $model = $this->createTestRemindableModel();

        // Mix de formats avec des strings pour les dates
        $scheduledTimes = [
            Carbon::tomorrow()->setTime(9, 0)->format('Y-m-d H:i:s'),
            Carbon::tomorrow()->setTime(12, 0)->format('Y-m-d H:i:s') => ['sms'],
            Carbon::tomorrow()->setTime(15, 0)->format('Y-m-d H:i:s'),
        ];

        // Act
        $reminders = $model->scheduleMultipleReminders($scheduledTimes, [], ['mail']);

        // Assert
        $this->assertCount(3, $reminders);

        $this->assertEquals(['mail'], $reminders[0]->channels());
        $this->assertEquals(['sms'], $reminders[1]->channels());
        $this->assertEquals(['mail'], $reminders[2]->channels());
    }

    /**
     * Test that scheduleMultipleReminders works with array of dates only.
     *
     * @return void
     */
    public function test_schedule_multiple_reminders_with_dates_only(): void
    {
        // Arrange
        $model = $this->createTestRemindableModel();

        $dates = [
            Carbon::tomorrow()->setTime(9, 0),
            Carbon::tomorrow()->setTime(12, 0),
        ];

        // Act
        $reminders = $model->scheduleMultipleReminders($dates);

        // Assert
        $this->assertCount(2, $reminders);

        foreach ($reminders as $reminder) {
            $this->assertIsArray($reminder->channels);
            $this->assertEmpty($reminder->channels);
            $this->assertFalse($reminder->has_custom_channels);
        }
    }

    /**
     * Test that scheduleMultipleReminders with empty array throws exception.
     *
     * @return void
     */
    public function test_schedule_multiple_reminders_with_empty_array_throws_exception(): void
    {
        // Arrange
        $model = $this->createTestRemindableModel();

        // Assert
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Scheduled times array cannot be empty');

        // Act
        $model->scheduleMultipleReminders([]);
    }

    /**
     * Test that scheduleMultipleReminders validates each date.
     *
     * @return void
     */
    public function test_schedule_multiple_reminders_validates_each_date(): void
    {
        // Arrange
        $model = $this->createTestRemindableModel();

        $scheduledTimes = [
            Carbon::tomorrow()->setTime(9, 0),
            Carbon::yesterday(), // Date passée - devrait échouer
        ];

        // Assert
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot schedule a reminder in the past');

        // Act
        $model->scheduleMultipleReminders($scheduledTimes);
    }

    /**
     * Test that reminders with channels appear in pending reminders.
     *
     * @return void
     */
    public function test_pending_reminders_includes_reminders_with_channels(): void
    {
        // Arrange
        $model = $this->createTestRemindableModel();

        $model->scheduleReminder(Carbon::tomorrow(), [], ['mail']);
        $model->scheduleReminder(Carbon::tomorrow()->addDay(), [], ['sms', 'push']);
        $sentReminder = $model->scheduleReminder(Carbon::tomorrow()->addDays(2), [], ['slack']);
        $sentReminder->markAsSent();

        // Act
        $pendingReminders = $model->pendingReminders();

        // Assert
        $this->assertCount(2, $pendingReminders);

        $channelsList = $pendingReminders->pluck('channels')->toArray();
        $this->assertContains(['mail'], $channelsList);
        $this->assertContains(['sms', 'push'], $channelsList);
    }

    /**
     * Test that nextReminder respects channels.
     *
     * @return void
     */
    public function test_next_reminder_respects_channels(): void
    {
        // Arrange
        $model = $this->createTestRemindableModel();

        $later = Carbon::tomorrow()->setTime(18, 0);
        $sooner = Carbon::tomorrow()->setTime(9, 0);

        $model->scheduleReminder($later, [], ['sms']);
        $model->scheduleReminder($sooner, [], ['mail', 'database']);

        // Act
        $nextReminder = $model->nextReminder();

        // Assert
        $this->assertNotNull($nextReminder);
        $this->assertEquals($sooner->toDateTimeString(), $nextReminder->scheduled_at->toDateTimeString());
        $this->assertEquals(['mail', 'database'], $nextReminder->channels);
    }
}
