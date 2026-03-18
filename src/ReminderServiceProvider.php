<?php

declare(strict_types=1);

namespace Andydefer\LaravelReminder;

use Andydefer\LaravelReminder\Console\Commands\SendRemindersCommand;
use Andydefer\LaravelReminder\Jobs\ProcessRemindersJob;
use Andydefer\LaravelReminder\Services\ReminderService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Config;

/**
 * Laravel service provider for the Reminder package.
 *
 * This service provider handles registration and bootstrapping of all
 * reminder package components including configuration, migrations,
 * console commands, and the scheduled job.
 *
 * @package Andydefer\LaravelReminder
 */
class ReminderServiceProvider extends ServiceProvider
{
    /**
     * The package's configuration file name.
     */
    private const CONFIG_FILE = 'reminder.php';

    /**
     * Register any application services.
     *
     * This method merges the package configuration and registers
     * the required services in the container.
     *
     * @return void
     */
    public function register(): void
    {
        $this->mergePackageConfiguration();
        $this->registerReminderService();
    }

    /**
     * Bootstrap any application services.
     *
     * This method registers all package resources and functionality:
     * - Publishes configuration and migrations
     * - Registers console commands
     * - Registers the scheduled job
     *
     * @return void
     */
    public function boot(): void
    {
        $this->registerPublishableResources();
        $this->registerConsoleCommands();
        $this->registerSchedule();
        $this->loadMigrations();
    }

    /**
     * Merge the package configuration with the application's configuration.
     *
     * @return void
     */
    private function mergePackageConfiguration(): void
    {
        $this->mergeConfigFrom(
            path: $this->getPackageConfigPath(),
            key: 'reminder'
        );
    }

    /**
     * Register the ReminderService as a singleton in the container.
     *
     * @return void
     */
    private function registerReminderService(): void
    {
        $this->app->singleton(ReminderService::class, function ($app) {
            $service = new ReminderService(
                config: Config::get('reminder', [])
            );

            if (isset($app['events'])) {
                $service->setEventDispatcher($app['events']);
            }

            return $service;
        });
    }

    /**
     * Load migrations.
     *
     * @return void
     */
    private function loadMigrations(): void
    {
        if ($this->app->runningInConsole()) {
            $migrationPath = $this->getMigrationPath();

            if ($migrationPath && file_exists($migrationPath)) {
                $this->loadMigrationsFrom($migrationPath);
            }
        }
    }

    /**
     * Register all publishable resources for the package.
     *
     * @return void
     */
    private function registerPublishableResources(): void
    {
        if (!$this->app->runningInConsole()) {
            return;
        }

        $this->publishesConfiguration();
        $this->publishesPackageMigrations();
    }

    /**
     * Publish the package configuration file.
     *
     * @return void
     */
    private function publishesConfiguration(): void
    {
        $configPath = $this->getPackageConfigPath();

        if (file_exists($configPath)) {
            $this->publishes(
                paths: [
                    $configPath => config_path(self::CONFIG_FILE),
                ],
                groups: 'reminder-config'
            );
        }
    }

    /**
     * Publish the package migration files.
     *
     * @return void
     */
    private function publishesPackageMigrations(): void
    {
        $migrationPath = $this->getMigrationPath();

        if ($migrationPath && is_dir($migrationPath)) {
            $this->publishes(
                paths: [
                    $migrationPath => database_path('migrations'),
                ],
                groups: 'reminder-migrations'
            );
        }
    }

    /**
     * Register the package's console commands.
     *
     * @return void
     */
    private function registerConsoleCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                SendRemindersCommand::class,
            ]);
        }
    }

    /**
     * Register the scheduled job.
     *
     * @return void
     */
    private function registerSchedule(): void
    {
        if (!$this->app->runningInConsole()) {
            return;
        }

        $this->app->booted(function () {
            $schedule = $this->app->make(Schedule::class);

            if (Config::get('reminder.queue.enabled', true)) {
                $this->scheduleReminderJob($schedule);
            }
        });
    }

    /**
     * Schedule the reminder processing job.
     *
     * @param Schedule $schedule
     * @return void
     */
    private function scheduleReminderJob(Schedule $schedule): void
    {
        $frequency = Config::get('reminder.schedule_frequency', 15);

        $event = $schedule->job(
            job: new ProcessRemindersJob(),
            queue: Config::get('reminder.queue.name', 'default')
        );

        // Schedule based on frequency
        if ($frequency === 15) {
            $event->everyFifteenSeconds();
        } elseif ($frequency === 30) {
            $event->everyThirtySeconds();
        } elseif ($frequency === 60) {
            $event->everyMinute();
        } else {
            // Custom frequency using cron
            $event->cron("*/{$frequency} * * * * *");
        }

        $event->withoutOverlapping()
            ->name('process-reminders')
            ->onOneServer();
    }

    /**
     * Get the path to the package configuration file.
     *
     * @return string
     */
    private function getPackageConfigPath(): string
    {
        return __DIR__ . '/../config/' . self::CONFIG_FILE;
    }

    /**
     * Get the migration path.
     *
     * @return string|null
     */
    private function getMigrationPath(): ?string
    {
        $path = __DIR__ . '/Database/Migrations';

        return is_dir($path) ? $path : null;
    }
}
