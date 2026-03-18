<?php

declare(strict_types=1);

namespace Andydefer\LaravelReminder\Jobs;

use Andydefer\LaravelReminder\Services\ReminderService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessRemindersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 1;

    /**
     * The maximum number of unhandled exceptions to allow before failing.
     */
    public int $maxExceptions = 1;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 120;

    /**
     * Indicate if the job should be marked as failed on timeout.
     */
    public bool $failOnTimeout = true;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        $this->onConnection(config('reminder.queue.connection', config('queue.default')));
        $this->onQueue(config('reminder.queue.name', 'default'));
    }

    /**
     * Execute the job.
     */
    public function handle(ReminderService $reminderService): void
    {
        Log::info('Processing pending reminders');

        $startTime = microtime(true);
        $result = $reminderService->processPendingReminders();
        $executionTime = round((microtime(true) - $startTime) * 1000, 2);

        Log::info('Reminders processed', array_merge($result, [
            'execution_time_ms' => $executionTime,
            'job_id' => $this->job?->getJobId(),
        ]));

        // Release if we have more to process and not at limit
        if ($result['total'] >= 100 && $this->attempts() < 3) {
            $this->release(30); // Release back to queue after 30 seconds
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('ProcessRemindersJob failed', [
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
