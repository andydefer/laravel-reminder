<?php

declare(strict_types=1);

namespace Andydefer\LaravelReminder\Tests\Unit\Models;

use Andydefer\LaravelReminder\Enums\ReminderStatus;
use Andydefer\LaravelReminder\Models\Reminder;
use Andydefer\LaravelReminder\Tests\Fixtures\TestRemindableModel;
use Andydefer\LaravelReminder\Tests\TestCase;
use Carbon\Carbon;

class ReminderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2025-01-01 10:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function createReminder($model, Carbon $scheduledAt, array $attributes = []): Reminder
    {
        $reminder = new Reminder(array_merge([
            'remindable_type' => get_class($model),
            'remindable_id' => $model->id,
            'scheduled_at' => $scheduledAt,
            'status' => ReminderStatus::PENDING,
            'attempts' => 0,
            'metadata' => [],
        ], $attributes));
        $reminder->save();
        return $reminder;
    }

    public function test_can_create_reminder_with_all_attributes(): void
    {
        $model = $this->createTestRemindableModel();
        $scheduledAt = Carbon::tomorrow()->setTime(10, 0);
        $metadata = ['foo' => 'bar', 'baz' => 123];

        $reminder = $this->createReminder($model, $scheduledAt, [
            'metadata' => $metadata,
            'status' => ReminderStatus::PENDING,
            'attempts' => 0,
        ]);

        $this->assertInstanceOf(Reminder::class, $reminder);
        $this->assertEquals($scheduledAt->toDateTimeString(), $reminder->scheduled_at->toDateTimeString());
        $this->assertEquals($metadata, $reminder->metadata);
        $this->assertEquals(ReminderStatus::PENDING, $reminder->status);
        $this->assertEquals(0, $reminder->attempts);
        $this->assertNull($reminder->sent_at);
        $this->assertNull($reminder->error_message);
    }

    public function test_pending_scope_returns_only_pending_reminders(): void
    {
        $model = $this->createTestRemindableModel();

        $pending1 = $this->createReminder($model, Carbon::tomorrow());
        $pending2 = $this->createReminder($model, Carbon::tomorrow()->addDay());
        $sent = $this->createReminder($model, Carbon::tomorrow()->addDays(2));
        $sent->markAsSent();
        $failed = $this->createReminder($model, Carbon::tomorrow()->addDays(3));

        // Forcer 3 échecs pour que le statut devienne FAILED
        $failed->markAsFailed('Test failure 1');
        $failed->markAsFailed('Test failure 2');
        $failed->markAsFailed('Test failure 3');

        // Rafraîchir pour s'assurer que les changements sont pris en compte
        $sent->refresh();
        $failed->refresh();

        $pendingReminders = Reminder::pending()->get();

        $this->assertCount(2, $pendingReminders);
        $this->assertTrue($pendingReminders->contains('id', $pending1->id));
        $this->assertTrue($pendingReminders->contains('id', $pending2->id));
        $this->assertFalse($pendingReminders->contains('id', $sent->id));
        $this->assertFalse($pendingReminders->contains('id', $failed->id));
    }

    public function test_due_scope_returns_only_due_reminders(): void
    {
        $model = $this->createTestRemindableModel();

        $due = $this->createReminder($model, Carbon::now()->subMinutes(5));
        $future = $this->createReminder($model, Carbon::now()->addHours(2));
        $exceededAttempts = $this->createReminder($model, Carbon::now()->subMinutes(10), ['attempts' => 3]);

        $dueReminders = Reminder::due()->get();

        $this->assertCount(1, $dueReminders);
        $this->assertEquals($due->id, $dueReminders->first()->id);
    }

    public function test_within_tolerance_scope_filters_by_tolerance(): void
    {
        $model = $this->createTestRemindableModel();

        $within = $this->createReminder($model, Carbon::now()->addMinutes(15));
        $within2 = $this->createReminder($model, Carbon::now()->subMinutes(10));
        $outside = $this->createReminder($model, Carbon::now()->addHours(2));

        $withinTolerance = Reminder::withinTolerance(30)->get();

        $this->assertCount(2, $withinTolerance);
        $this->assertTrue($withinTolerance->contains('id', $within->id));
        $this->assertTrue($withinTolerance->contains('id', $within2->id));
        $this->assertFalse($withinTolerance->contains('id', $outside->id));
    }

    public function test_mark_as_sent(): void
    {
        $model = $this->createTestRemindableModel();
        $reminder = $this->createReminder($model, Carbon::tomorrow());

        $reminder->markAsSent();
        $reminder->refresh();

        $this->assertEquals(ReminderStatus::SENT, $reminder->status);
        $this->assertNotNull($reminder->sent_at);
        $this->assertNotNull($reminder->last_attempt_at);
    }

    public function test_mark_as_failed(): void
    {
        $model = $this->createTestRemindableModel();
        $reminder = $this->createReminder($model, Carbon::tomorrow());
        $errorMessage = 'Connection timeout';

        $reminder->markAsFailed($errorMessage);
        $reminder->refresh();

        $this->assertEquals(ReminderStatus::PENDING, $reminder->status);
        $this->assertEquals(1, $reminder->attempts);
        $this->assertEquals($errorMessage, $reminder->error_message);
        $this->assertNotNull($reminder->last_attempt_at);
    }

    public function test_becomes_failed_after_max_attempts(): void
    {
        $model = $this->createTestRemindableModel();
        $reminder = $this->createReminder($model, Carbon::tomorrow());

        $reminder->markAsFailed('Attempt 1');
        $reminder->markAsFailed('Attempt 2');
        $reminder->markAsFailed('Attempt 3');
        $reminder->refresh();

        $this->assertEquals(ReminderStatus::FAILED, $reminder->status);
        $this->assertEquals(3, $reminder->attempts);
    }

    public function test_cancel_reminder(): void
    {
        $model = $this->createTestRemindableModel();
        $reminder = $this->createReminder($model, Carbon::tomorrow());

        $reminder->cancel();
        $reminder->refresh();

        $this->assertEquals(ReminderStatus::CANCELLED, $reminder->status);
    }

    public function test_remindable_relationship_returns_owning_model(): void
    {
        $model = $this->createTestRemindableModel();
        $reminder = $this->createReminder($model, Carbon::tomorrow());

        $remindable = $reminder->remindable;

        $this->assertInstanceOf(TestRemindableModel::class, $remindable);
        $this->assertEquals($model->id, $remindable->id);
    }
}
