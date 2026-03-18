<?php

declare(strict_types=1);

namespace Andydefer\LaravelReminder\Tests;

use Andydefer\LaravelReminder\ReminderServiceProvider;
use Andydefer\LaravelReminder\Tests\Fixtures\TestRemindableModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

/**
 * Base test case for the Laravel Reminder package.
 *
 * This abstract class provides the foundation for all tests in the package,
 * setting up the test environment, database configuration, and common helper
 * methods for creating test fixtures.
 *
 * @package Andydefer\LaravelReminder\Tests
 */
abstract class TestCase extends OrchestraTestCase
{
    use LazilyRefreshDatabase;

    /**
     * Default test model attributes.
     */
    private const DEFAULT_MODEL_ATTRIBUTES = [
        'name' => 'Test Model',
        'email' => 'test@example.com',
    ];

    /**
     * Set up the test environment before each test.
     *
     * This method is called before every test method to ensure a clean
     * and properly configured testing environment.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->disableLoggingAndQueue();
        $this->loadNotificationsMigrations();
    }

    /**
     * Get the package service providers that should be loaded.
     *
     * @param \Illuminate\Foundation\Application $app
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            ReminderServiceProvider::class,
        ];
    }

    /**
     * Define the test environment configuration.
     *
     * @param \Illuminate\Foundation\Application $app
     * @return void
     */
    protected function defineEnvironment($app): void
    {
        $this->configureTestDatabase($app);
        $this->configurePackageSettings($app);
        $this->configureNotificationsTable($app);
    }

    /**
     * Define the database migrations for testing.
     *
     * @return void
     */
    protected function defineDatabaseMigrations(): void
    {
        // Charger les migrations du package
        $packageMigrationPath = realpath(__DIR__ . '/../src/Database/Migrations');
        if ($packageMigrationPath && is_dir($packageMigrationPath)) {
            $this->loadMigrationsFrom($packageMigrationPath);
        }

        // Charger les migrations de test (y compris notifications)
        $testMigrationPath = __DIR__ . '/database/migrations';
        if (is_dir($testMigrationPath)) {
            $this->loadMigrationsFrom($testMigrationPath);
        }
    }

    /**
     * Configure the test database connection.
     *
     * @param \Illuminate\Foundation\Application $app
     * @return void
     */
    private function configureTestDatabase($app): void
    {
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
    }

    /**
     * Configure package-specific settings for testing.
     *
     * @param \Illuminate\Foundation\Application $app
     * @return void
     */
    private function configurePackageSettings($app): void
    {
        $app['config']->set('reminder.default_tolerance', [
            'value' => 30,
            'unit' => \Andydefer\LaravelReminder\Enums\ToleranceUnit::MINUTE,
        ]);
        $app['config']->set('reminder.max_attempts', 3);
        $app['config']->set('reminder.queue.enabled', false);
        $app['config']->set('reminder.logging.enabled', false);
        $app['config']->set('reminder.cleanup.enabled', false);
    }

    /**
     * Configure notifications table settings.
     *
     * @param \Illuminate\Foundation\Application $app
     * @return void
     */
    private function configureNotificationsTable($app): void
    {
        // S'assurer que la table notifications utilise la bonne connexion
        $app['config']->set('database.connections.testbench.notifications', [
            'table' => 'notifications',
        ]);
    }

    /**
     * Disable logging and queue features for testing.
     *
     * @return void
     */
    private function disableLoggingAndQueue(): void
    {
        Config::set('reminder.logging.enabled', false);
        Config::set('reminder.queue.enabled', false);
    }

    /**
     * Load notifications migrations if not already loaded.
     *
     * @return void
     */
    private function loadNotificationsMigrations(): void
    {
        // Vérifier si la table notifications existe déjà
        if (!Schema::hasTable('notifications')) {
            // Créer la table notifications manuellement
            Schema::create('notifications', function ($table) {
                $table->uuid('id')->primary();
                $table->string('type');
                $table->morphs('notifiable');
                $table->text('data');
                $table->timestamp('read_at')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Create a test remindable model instance.
     *
     * @param array<string, mixed> $attributes Custom attributes to override defaults
     * @return TestRemindableModel
     */
    protected function createTestRemindableModel(array $attributes = []): TestRemindableModel
    {
        $modelAttributes = array_merge(self::DEFAULT_MODEL_ATTRIBUTES, $attributes);

        return TestRemindableModel::create($modelAttributes);
    }
}
