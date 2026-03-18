<?php

declare(strict_types=1);

namespace Andydefer\LaravelReminder\Tests\Unit\Services;

use Andydefer\LaravelReminder\Enums\ReminderStatus;
use Andydefer\LaravelReminder\Models\Reminder;
use Andydefer\LaravelReminder\Services\ReminderService;
use Andydefer\LaravelReminder\Tests\Fixtures\InvalidTestRemindable;
use Andydefer\LaravelReminder\Tests\TestCase;
use Carbon\Carbon;
use Illuminate\Support\Facades\Event;
use Mockery;

class ReminderServiceTest extends TestCase
{
    private ReminderService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2025-01-01 10:00:00'));
        $this->service = app(ReminderService::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Mockery::close();
        parent::tearDown();
    }

    private function createReminder($model, Carbon $scheduledAt): Reminder
    {
        $reminder = new Reminder([
            'remindable_type' => get_class($model),
            'remindable_id' => $model->id,
            'scheduled_at' => $scheduledAt,
            'status' => ReminderStatus::PENDING,
            'attempts' => 0,
            'metadata' => [],
        ]);
        $reminder->save();
        return $reminder;
    }

    public function test_processes_valid_reminder(): void
    {
        $model = $this->createTestRemindableModel();
        $reminder = $this->createReminder($model, Carbon::now()->subMinutes(5));

        $result = $this->service->processReminder($reminder);

        $this->assertTrue($result);
        $this->assertEquals(ReminderStatus::SENT, $reminder->fresh()->status);
        $this->assertNotNull($reminder->fresh()->sent_at);
    }

    public function test_does_not_process_reminder_outside_tolerance(): void
    {
        $model = $this->createTestRemindableModel();
        $reminder = $this->createReminder($model, Carbon::now()->subHours(2));

        $result = $this->service->processReminder($reminder);

        $this->assertFalse($result);
        $fresh = $reminder->fresh();
        $this->assertEquals(ReminderStatus::PENDING, $fresh->status);
        $this->assertEquals(1, $fresh->attempts);
        $this->assertStringContainsString('Outside tolerance', $fresh->error_message);
    }

    public function test_processes_all_pending_reminders(): void
    {
        $model = $this->createTestRemindableModel();

        $reminder1 = $this->createReminder($model, Carbon::now()->subMinutes(15));
        $reminder2 = $this->createReminder($model, Carbon::now()->subMinutes(20));
        $reminder3 = $this->createReminder($model, Carbon::now()->subHours(2));
        $reminder4 = $this->createReminder($model, Carbon::now()->addDays(1));

        $result = $this->service->processPendingReminders();

        $reminder1->refresh();
        $reminder2->refresh();
        $reminder3->refresh();
        $reminder4->refresh();

        $this->assertEquals(2, $result['processed']);
        $this->assertEquals(1, $result['failed']);
        $this->assertEquals(3, $result['total']);

        $this->assertEquals(ReminderStatus::SENT, $reminder1->status);
        $this->assertEquals(ReminderStatus::SENT, $reminder2->status);
        $this->assertEquals(ReminderStatus::PENDING, $reminder3->status);
        $this->assertEquals(1, $reminder3->attempts);
        $this->assertEquals(ReminderStatus::PENDING, $reminder4->status);
    }

    public function test_handles_invalid_remindable(): void
    {
        $model = new InvalidTestRemindable();
        $model->name = 'Invalid Model';
        $model->email = 'invalid@example.com';
        $model->save();

        $reminder = $this->createReminder($model, Carbon::now()->subMinutes(15));

        $result = $this->service->processReminder($reminder);

        $this->assertFalse($result);
        $fresh = $reminder->fresh();
        $this->assertEquals(ReminderStatus::PENDING, $fresh->status);
        $this->assertEquals(1, $fresh->attempts);
        $this->assertStringContainsString('does not implement ShouldRemind', $fresh->error_message);
    }

    public function test_dispatches_events_during_processing(): void
    {
        Event::fake();

        $model = $this->createTestRemindableModel();
        $reminder = $this->createReminder($model, Carbon::now()->subMinutes(15));

        $service = new ReminderService(
            config: $this->service->getConfig(),
            events: app('events')
        );

        $service->processReminder($reminder);

        $this->assertEquals(ReminderStatus::SENT, $reminder->fresh()->status);

        // Vérifier simplement que les événements ont été dispatchés, sans vérifier le payload
        Event::assertDispatched('reminder.processing');
        Event::assertDispatched('reminder.sent');
    }
    public function test_handles_exceptions_during_sending(): void
    {
        $model = $this->createTestRemindableModel();
        $reminder = $this->createReminder($model, Carbon::now()->subMinutes(15));

        $testService = new class($this->service->getConfig(), app('events')) extends ReminderService {
            protected function sendNotification(Reminder $reminder, array $data): void
            {
                throw new \Exception('Simulated sending error');
            }
        };

        $result = $testService->processReminder($reminder);

        $this->assertFalse($result);
        $fresh = $reminder->fresh();
        $this->assertEquals(ReminderStatus::PENDING, $fresh->status);
        $this->assertEquals(1, $fresh->attempts);
        $this->assertStringContainsString('Simulated sending error', $fresh->error_message);
    }

    public function test_respects_max_attempts(): void
    {
        $model = $this->createTestRemindableModel();
        $reminder = $this->createReminder($model, Carbon::now()->subMinutes(15));
        $reminder->attempts = 2;
        $reminder->save();

        $testService = new class($this->service->getConfig(), app('events')) extends ReminderService {
            protected function sendNotification(Reminder $reminder, array $data): void
            {
                throw new \Exception('Persistent failure');
            }
        };

        $result = $testService->processReminder($reminder);

        $this->assertFalse($result);
        $fresh = $reminder->fresh();
        $this->assertEquals(ReminderStatus::FAILED, $fresh->status);
        $this->assertEquals(3, $fresh->attempts);
    }
}
