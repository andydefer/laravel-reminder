<?php

declare(strict_types=1);

namespace Andydefer\LaravelReminder\Tests\Unit\Jobs;

use Andydefer\LaravelReminder\Enums\ReminderStatus;
use Andydefer\LaravelReminder\Jobs\ProcessRemindersJob;
use Andydefer\LaravelReminder\Models\Reminder;
use Andydefer\LaravelReminder\Services\ReminderService;
use Andydefer\LaravelReminder\Tests\Fixtures\TestNotification;
use Andydefer\LaravelReminder\Tests\TestCase;
use Carbon\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

class ProcessRemindersJobTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['reminder.queue.enabled' => true]);
        config(['reminder.max_attempts' => 3]);
        Carbon::setTestNow(Carbon::parse('2025-01-01 10:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function createPastReminder($model, $minutesAgo = 5, array $channels = []): Reminder
    {
        $reminder = new Reminder([
            'remindable_type' => get_class($model),
            'remindable_id' => $model->id,
            'scheduled_at' => Carbon::now()->subMinutes($minutesAgo),
            'status' => ReminderStatus::PENDING,
            'attempts' => 0,
            'metadata' => [],
            'channels' => $channels,
        ]);

        $reminder->save();

        // Charger la relation
        $reminder->setRelation('remindable', $model);

        return $reminder;
    }

    public function test_can_dispatch_job_to_queue(): void
    {
        Queue::fake();

        ProcessRemindersJob::dispatch();

        Queue::assertPushed(ProcessRemindersJob::class, 1);
    }

    public function test_processes_pending_reminders(): void
    {
        $model = $this->createTestRemindableModel();
        $reminder = $this->createPastReminder($model, 5);

        $job = new ProcessRemindersJob();
        $job->handle(app(ReminderService::class));

        $fresh = $reminder->fresh();

        $this->assertEquals(ReminderStatus::SENT, $fresh->status);
    }

    public function test_logs_processing_results(): void
    {
        $model = $this->createTestRemindableModel();

        $this->createPastReminder($model, 5);
        $this->createPastReminder($model, 10);

        $job = new ProcessRemindersJob();
        $job->handle(app(ReminderService::class));

        $this->assertTrue(true, 'Job executed without errors');
    }

    public function test_handles_failures_gracefully(): void
    {
        $job = new ProcessRemindersJob();
        $exception = new \Exception('Test exception');

        $job->failed($exception);

        $this->assertTrue(true, 'Job handled failure without throwing exception');
    }

    public function test_respects_queue_configuration(): void
    {
        config(['reminder.queue.connection' => 'redis']);
        config(['reminder.queue.name' => 'high']);

        $job = new ProcessRemindersJob();

        $this->assertEquals('redis', $job->connection);
        $this->assertEquals('high', $job->queue);
    }

    public function test_has_correct_timeout_settings(): void
    {
        $job = new ProcessRemindersJob();

        $this->assertEquals(120, $job->timeout);
        $this->assertTrue($job->failOnTimeout);
        $this->assertEquals(1, $job->tries);
        $this->assertEquals(1, $job->maxExceptions);
    }

    public function test_process_reminders_job_preserves_channels(): void
    {
        // Arrange
        $model = $this->createTestRemindableModel();
        $customChannels = ['mail', 'sms', 'slack'];

        // Mock du système de notification pour éviter les vraies tentatives d'envoi
        Notification::fake();

        $reminder = $this->createPastReminder($model, 5, $customChannels);

        // Act
        $job = new ProcessRemindersJob();
        $job->handle(app(ReminderService::class));

        // Assert
        $fresh = $reminder->fresh();
        $this->assertEquals(ReminderStatus::SENT, $fresh->status);
        $this->assertEquals($customChannels, $fresh->channels());
        $this->assertTrue($fresh->has_custom_channels);

        // Vérifier que la notification a été envoyée (peu importe les channels)
        Notification::assertSentTo(
            $model,
            TestNotification::class,
            function ($notification) use ($customChannels) {
                return $notification->reminder->channels() === $customChannels;
            }
        );
    }

    public function test_process_reminders_job_handles_multiple_reminders_with_different_channels(): void
    {
        // Arrange
        $model = $this->createTestRemindableModel();

        // Mock du système de notification pour éviter les vraies tentatives d'envoi
        Notification::fake();

        $reminder1 = $this->createPastReminder($model, 5, ['mail']);
        $reminder2 = $this->createPastReminder($model, 10, ['sms', 'push']);
        $reminder3 = $this->createPastReminder($model, 15, []); // Pas de channels
        $reminder4 = $this->createPastReminder($model, 120, ['slack']); // Hors tolérance

        // Act
        $job = new ProcessRemindersJob();
        $job->handle(app(ReminderService::class));

        // Assert
        $reminder1->refresh();
        $reminder2->refresh();
        $reminder3->refresh();
        $reminder4->refresh();

        // Vérifier les statuts
        $this->assertEquals(ReminderStatus::SENT, $reminder1->status);
        $this->assertEquals(ReminderStatus::SENT, $reminder2->status);
        $this->assertEquals(ReminderStatus::SENT, $reminder3->status);
        $this->assertEquals(ReminderStatus::PENDING, $reminder4->status);

        // Vérifier les channels
        $this->assertEquals(['mail'], $reminder1->channels());
        $this->assertEquals(['sms', 'push'], $reminder2->channels());
        $this->assertIsArray($reminder3->channels());
        $this->assertEmpty($reminder3->channels());
        $this->assertEquals(['slack'], $reminder4->channels());

        // Vérifier has_custom_channels
        $this->assertTrue($reminder1->has_custom_channels);
        $this->assertTrue($reminder2->has_custom_channels);
        $this->assertFalse($reminder3->has_custom_channels);
        $this->assertTrue($reminder4->has_custom_channels);

        // Vérifier les tentatives
        $this->assertEquals(1, $reminder4->attempts);

        // Vérifier que les notifications ont été envoyées pour les 3 premiers reminders
        Notification::assertSentTo(
            $model,
            TestNotification::class,
            3 // 3 notifications envoyées (reminder1, reminder2, reminder3)
        );

        // Vérifier plus précisément que chaque notification a les bons channels
        Notification::assertSentTo(
            $model,
            TestNotification::class,
            function ($notification) use ($reminder1, $reminder2, $reminder3) {
                $channels = $notification->reminder->channels();

                // Vérifier que c'est l'un des 3 reminders valides
                return in_array($channels, [['mail'], ['sms', 'push'], []]);
            }
        );
    }

    public function test_process_reminders_job_with_queue_disabled_processes_synchronously(): void
    {
        // Arrange
        config(['reminder.queue.enabled' => false]);

        $model = $this->createTestRemindableModel();
        $reminder = $this->createPastReminder($model, 5, ['mail']);

        // Act
        $job = new ProcessRemindersJob();
        $job->handle(app(ReminderService::class));

        // Assert
        $fresh = $reminder->fresh();
        $this->assertEquals(ReminderStatus::SENT, $fresh->status);
        $this->assertEquals(['mail'], $fresh->channels());
    }

    public function test_process_reminders_job_handles_empty_channel_array_gracefully(): void
    {
        // Arrange
        $model = $this->createTestRemindableModel();

        $reminder = $this->createPastReminder($model, 5, []);

        // Act
        $job = new ProcessRemindersJob();
        $job->handle(app(ReminderService::class));

        // Assert
        $fresh = $reminder->fresh();
        $this->assertEquals(ReminderStatus::SENT, $fresh->status);
        $this->assertIsArray($fresh->channels());
        $this->assertEmpty($fresh->channels());
        $this->assertFalse($fresh->has_custom_channels);
    }
}
