<?php

declare(strict_types=1);

namespace Andydefer\LaravelReminder\Console\Commands;

use Andydefer\LaravelReminder\Jobs\ProcessRemindersJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendRemindersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reminders:send
                            {--sync : Process reminders synchronously without dispatching a job}
                            {--queue= : The queue to dispatch the job to}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send pending reminders';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting reminder processing...');

        if ($this->option('sync')) {
            return $this->processSynchronously();
        }

        return $this->dispatchJob();
    }

    /**
     * Process reminders synchronously.
     */
    protected function processSynchronously(): int
    {
        $this->info('Processing reminders synchronously...');

        try {
            $service = app(\Andydefer\LaravelReminder\Services\ReminderService::class);
            $result = $service->processPendingReminders();

            $this->table(
                ['Metric', 'Count'],
                [
                    ['Total', $result['total']],
                    ['Processed', $result['processed']],
                    ['Failed', $result['failed']],
                ]
            );

            $this->info('Reminders processed successfully!');

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Failed to process reminders: ' . $e->getMessage());
            Log::error('Reminders sync processing failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return Command::FAILURE;
        }
    }

    /**
     * Dispatch the job to the queue.
     */
    protected function dispatchJob(): int
    {
        $job = new ProcessRemindersJob();

        if ($queue = $this->option('queue')) {
            $job->onQueue($queue);
        }

        dispatch($job);

        $this->info('Reminder processing job dispatched to queue successfully.');

        return Command::SUCCESS;
    }
}
