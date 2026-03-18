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
            'channels' => [], // Ajout du champ channels
        ], $attributes));
        $reminder->save();
        return $reminder;
    }

    public function test_can_create_reminder_with_all_attributes(): void
    {
        $model = $this->createTestRemindableModel();
        $scheduledAt = Carbon::tomorrow()->setTime(10, 0);
        $metadata = ['foo' => 'bar', 'baz' => 123];
        $channels = ['mail', 'database', 'slack'];

        $reminder = $this->createReminder($model, $scheduledAt, [
            'metadata' => $metadata,
            'channels' => $channels,
            'status' => ReminderStatus::PENDING,
            'attempts' => 0,
        ]);

        $this->assertInstanceOf(Reminder::class, $reminder);
        $this->assertEquals($scheduledAt->toDateTimeString(), $reminder->scheduled_at->toDateTimeString());
        $this->assertEquals($metadata, $reminder->metadata);
        $this->assertEquals($channels, $reminder->channels);
        $this->assertEquals(ReminderStatus::PENDING, $reminder->status);
        $this->assertEquals(0, $reminder->attempts);
        $this->assertNull($reminder->sent_at);
        $this->assertNull($reminder->error_message);
    }

    public function test_can_create_reminder_without_channels(): void
    {
        $model = $this->createTestRemindableModel();
        $scheduledAt = Carbon::tomorrow()->setTime(10, 0);

        $reminder = $this->createReminder($model, $scheduledAt, []);

        $this->assertInstanceOf(Reminder::class, $reminder);
        $this->assertIsArray($reminder->channels);
        $this->assertEmpty($reminder->channels);
        $this->assertFalse($reminder->has_custom_channels);
    }

    public function test_channels_method_returns_array_or_null(): void
    {
        $model = $this->createTestRemindableModel();

        // Reminder avec channels
        $reminderWithChannels = $this->createReminder($model, Carbon::tomorrow(), [
            'channels' => ['mail', 'sms']
        ]);

        // Reminder sans channels
        $reminderWithoutChannels = $this->createReminder($model, Carbon::tomorrow(), []);

        $this->assertEquals(['mail', 'sms'], $reminderWithChannels->channels());
        $this->assertEmpty($reminderWithoutChannels->channels());
        $this->assertIsArray($reminderWithoutChannels->channels());
    }

    public function test_channels_for_sending_returns_custom_channels_when_available(): void
    {
        $model = $this->createTestRemindableModel();

        $reminder = $this->createReminder($model, Carbon::tomorrow(), [
            'channels' => ['sms', 'push']
        ]);

        $this->assertEquals(['sms', 'push'], $reminder->channelsForSending(['mail']));
        $this->assertEquals(['sms', 'push'], $reminder->channelsForSending(['mail', 'database']));
        $this->assertEquals(['sms', 'push'], $reminder->channelsForSending([]));
    }

    public function test_channels_for_sending_returns_fallback_when_no_custom_channels(): void
    {
        $model = $this->createTestRemindableModel();

        $reminder = $this->createReminder($model, Carbon::tomorrow(), []);

        $this->assertEquals(['mail'], $reminder->channelsForSending(['mail']));
        $this->assertEquals(['mail', 'database'], $reminder->channelsForSending(['mail', 'database']));
        $this->assertEquals([], $reminder->channelsForSending([]));
    }

    public function test_has_custom_channels_attribute_returns_true_when_channels_exist(): void
    {
        $model = $this->createTestRemindableModel();

        $reminderWithChannels = $this->createReminder($model, Carbon::tomorrow(), [
            'channels' => ['mail']
        ]);

        $reminderWithMultipleChannels = $this->createReminder($model, Carbon::tomorrow(), [
            'channels' => ['mail', 'sms', 'database']
        ]);

        $reminderWithoutChannels = $this->createReminder($model, Carbon::tomorrow(), []);

        $this->assertTrue($reminderWithChannels->has_custom_channels);
        $this->assertTrue($reminderWithMultipleChannels->has_custom_channels);
        $this->assertFalse($reminderWithoutChannels->has_custom_channels);
    }

    public function test_channels_are_preserved_after_marking_as_sent(): void
    {
        $model = $this->createTestRemindableModel();
        $channels = ['mail', 'slack'];

        $reminder = $this->createReminder($model, Carbon::tomorrow(), [
            'channels' => $channels
        ]);

        $reminder->markAsSent();
        $reminder->refresh();

        $this->assertEquals(ReminderStatus::SENT, $reminder->status);
        $this->assertEquals($channels, $reminder->channels);
        $this->assertTrue($reminder->has_custom_channels);
    }

    public function test_channels_are_preserved_after_failed_attempts(): void
    {
        $model = $this->createTestRemindableModel();
        $channels = ['sms', 'push'];

        $reminder = $this->createReminder($model, Carbon::tomorrow(), [
            'channels' => $channels
        ]);

        $reminder->markAsFailed('First attempt failed');
        $reminder->refresh();

        $this->assertEquals(1, $reminder->attempts);
        $this->assertEquals($channels, $reminder->channels);
        $this->assertTrue($reminder->has_custom_channels);

        $reminder->markAsFailed('Second attempt failed');
        $reminder->refresh();

        $this->assertEquals(2, $reminder->attempts);
        $this->assertEquals($channels, $reminder->channels);
        $this->assertTrue($reminder->has_custom_channels);
    }

    public function test_channels_are_preserved_after_cancel(): void
    {
        $model = $this->createTestRemindableModel();
        $channels = ['database'];

        $reminder = $this->createReminder($model, Carbon::tomorrow(), [
            'channels' => $channels
        ]);

        $reminder->cancel();
        $reminder->refresh();

        $this->assertEquals(ReminderStatus::CANCELLED, $reminder->status);
        $this->assertEquals($channels, $reminder->channels);
        $this->assertTrue($reminder->has_custom_channels);
    }

    public function test_pending_scope_returns_only_pending_reminders(): void
    {
        $model = $this->createTestRemindableModel();

        $pending1 = $this->createReminder($model, Carbon::tomorrow(), ['channels' => ['mail']]);
        $pending2 = $this->createReminder($model, Carbon::tomorrow()->addDay(), ['channels' => ['sms']]);
        $sent = $this->createReminder($model, Carbon::tomorrow()->addDays(2), ['channels' => ['mail']]);
        $sent->markAsSent();
        $failed = $this->createReminder($model, Carbon::tomorrow()->addDays(3), ['channels' => ['push']]);

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

        $due = $this->createReminder($model, Carbon::now()->subMinutes(5), ['channels' => ['mail']]);
        $future = $this->createReminder($model, Carbon::now()->addHours(2), ['channels' => ['sms']]);
        $exceededAttempts = $this->createReminder($model, Carbon::now()->subMinutes(10), ['attempts' => 3, 'channels' => ['push']]);

        $dueReminders = Reminder::due()->get();

        $this->assertCount(1, $dueReminders);
        $this->assertEquals($due->id, $dueReminders->first()->id);
    }

    public function test_within_tolerance_scope_filters_by_tolerance(): void
    {
        $model = $this->createTestRemindableModel();

        $within = $this->createReminder($model, Carbon::now()->addMinutes(15), ['channels' => ['mail']]);
        $within2 = $this->createReminder($model, Carbon::now()->subMinutes(10), ['channels' => ['sms']]);
        $outside = $this->createReminder($model, Carbon::now()->addHours(2), ['channels' => ['push']]);

        $withinTolerance = Reminder::withinTolerance(30)->get();

        $this->assertCount(2, $withinTolerance);
        $this->assertTrue($withinTolerance->contains('id', $within->id));
        $this->assertTrue($withinTolerance->contains('id', $within2->id));
        $this->assertFalse($withinTolerance->contains('id', $outside->id));
    }

    public function test_mark_as_sent(): void
    {
        $model = $this->createTestRemindableModel();
        $reminder = $this->createReminder($model, Carbon::tomorrow(), ['channels' => ['mail']]);

        $reminder->markAsSent();
        $reminder->refresh();

        $this->assertEquals(ReminderStatus::SENT, $reminder->status);
        $this->assertNotNull($reminder->sent_at);
        $this->assertNotNull($reminder->last_attempt_at);
    }

    public function test_mark_as_failed(): void
    {
        $model = $this->createTestRemindableModel();
        $reminder = $this->createReminder($model, Carbon::tomorrow(), ['channels' => ['mail']]);
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
        $reminder = $this->createReminder($model, Carbon::tomorrow(), ['channels' => ['mail']]);

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
        $reminder = $this->createReminder($model, Carbon::tomorrow(), ['channels' => ['mail']]);

        $reminder->cancel();
        $reminder->refresh();

        $this->assertEquals(ReminderStatus::CANCELLED, $reminder->status);
    }

    public function test_remindable_relationship_returns_owning_model(): void
    {
        $model = $this->createTestRemindableModel();
        $reminder = $this->createReminder($model, Carbon::tomorrow(), ['channels' => ['mail']]);

        $remindable = $reminder->remindable;

        $this->assertInstanceOf(TestRemindableModel::class, $remindable);
        $this->assertEquals($model->id, $remindable->id);
    }

    public function test_can_store_different_channel_combinations(): void
    {
        $model = $this->createTestRemindableModel();

        $channelCombinations = [
            ['mail'],
            ['mail', 'database'],
            ['sms', 'push', 'slack'],
            ['mail', 'sms', 'database', 'slack', 'push'],
        ];

        foreach ($channelCombinations as $channels) {
            $reminder = $this->createReminder($model, Carbon::tomorrow(), [
                'channels' => $channels
            ]);

            $this->assertEquals($channels, $reminder->channels);
            $this->assertCount(count($channels), $reminder->channels);
        }
    }
}
