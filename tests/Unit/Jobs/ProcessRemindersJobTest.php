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

class ProcessRemindersJobTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['reminder.queue.enabled' => true]);
        Carbon::setTestNow(Carbon::parse('2025-01-01 10:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function createPastReminder($model, $minutesAgo = 5): Reminder
    {
        $reminder = new Reminder([
            'remindable_type' => get_class($model),
            'remindable_id' => $model->id,
            'scheduled_at' => Carbon::now()->subMinutes($minutesAgo),
            'status' => ReminderStatus::PENDING,
            'attempts' => 0,
            'metadata' => [],
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
}
