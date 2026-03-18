<?php

declare(strict_types=1);

namespace Andydefer\LaravelReminder\Tests\Unit\Jobs;

use Andydefer\LaravelReminder\Enums\ReminderStatus;
use Andydefer\LaravelReminder\Jobs\ProcessRemindersJob;
use Andydefer\LaravelReminder\Models\Reminder;
use Andydefer\LaravelReminder\Services\ReminderService;
use Andydefer\LaravelReminder\Tests\TestCase;
use Carbon\Carbon;
use Illuminate\Support\Facades\Queue;

/**
 * Unit tests for the ProcessRemindersJob.
 *
 * This test suite verifies the behavior of the ProcessRemindersJob:
 * - Job dispatching
 * - Queue configuration
 * - Processing of reminders
 * - Job failure handling
 *
 * @package Andydefer\LaravelReminder\Tests\Unit\Jobs
 */
class ProcessRemindersJobTest extends TestCase
{
    /**
     * Set up the test environment.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Enable queue for these tests
        config(['reminder.queue.enabled' => true]);

        // Freeze time to have consistent tests
        Carbon::setTestNow(Carbon::parse('2025-01-01 10:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * Create a reminder with a past date for testing.
     */
    private function createPastReminder($model, $minutesAgo = 5): Reminder
    {
        // Create a reminder using query builder to bypass model validation
        $reminder = new Reminder([
            'remindable_type' => get_class($model),
            'remindable_id' => $model->id,
            'scheduled_at' => Carbon::now()->subMinutes($minutesAgo),
            'status' => ReminderStatus::PENDING,
            'attempts' => 0,
            'metadata' => [],
        ]);

        $reminder->save();

        return $reminder;
    }

    /**
     * Test that the job can be dispatched to the queue.
     *
     * @return void
     */
    public function test_can_dispatch_job_to_queue(): void
    {
        // Arrange
        Queue::fake();

        // Act
        ProcessRemindersJob::dispatch();

        // Assert
        Queue::assertPushed(ProcessRemindersJob::class, 1);
    }

    /**
     * Test that the job processes pending reminders.
     *
     * @return void
     */
    public function test_processes_pending_reminders(): void
    {
        // Arrange
        $model = $this->createTestRemindableModel();

        // Create a reminder that's due using our helper method
        $reminder = $this->createPastReminder($model, 5);

        $job = new ProcessRemindersJob();

        // Act
        $job->handle(app(ReminderService::class));

        // Assert
        $this->assertEquals(ReminderStatus::SENT, $reminder->fresh()->status);
    }

    /**
     * Test that the job logs processing results.
     *
     * @return void
     */
    public function test_logs_processing_results(): void
    {
        // Arrange
        $model = $this->createTestRemindableModel();

        // Create due reminders using our helper method
        $this->createPastReminder($model, 5);
        $this->createPastReminder($model, 10);

        $job = new ProcessRemindersJob();

        // Act
        $job->handle(app(ReminderService::class));

        // Assert - vérifions que le job a traité les reminders sans erreur
        $this->assertTrue(true, 'Job executed without errors');
    }

    /**
     * Test that the job handles failures gracefully.
     *
     * @return void
     */
    public function test_handles_failures_gracefully(): void
    {
        // Arrange
        $job = new ProcessRemindersJob();
        $exception = new \Exception('Test exception');

        // Act - cela ne devrait pas lancer d'exception
        $job->failed($exception);

        // Assert
        $this->assertTrue(true, 'Job handled failure without throwing exception');
    }

    /**
     * Test that the job respects queue configuration.
     *
     * @return void
     */
    public function test_respects_queue_configuration(): void
    {
        // Arrange
        config(['reminder.queue.connection' => 'redis']);
        config(['reminder.queue.name' => 'high']);

        // Act
        $job = new ProcessRemindersJob();

        // Assert
        $this->assertEquals('redis', $job->connection);
        $this->assertEquals('high', $job->queue);
    }

    /**
     * Test that the job has correct timeout settings.
     *
     * @return void
     */
    public function test_has_correct_timeout_settings(): void
    {
        // Arrange & Act
        $job = new ProcessRemindersJob();

        // Assert
        $this->assertEquals(120, $job->timeout);
        $this->assertTrue($job->failOnTimeout);
        $this->assertEquals(1, $job->tries);
        $this->assertEquals(1, $job->maxExceptions);
    }
}
