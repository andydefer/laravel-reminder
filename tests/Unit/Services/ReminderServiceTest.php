<?php

declare(strict_types=1);

namespace Andydefer\LaravelReminder\Tests\Unit\Services;

use Andydefer\LaravelReminder\Enums\ReminderStatus;
use Andydefer\LaravelReminder\Models\Reminder;
use Andydefer\LaravelReminder\Services\ReminderService;
use Andydefer\LaravelReminder\Tests\Fixtures\InvalidTestRemindable;
use Andydefer\LaravelReminder\Tests\Fixtures\TestNotification;
use Andydefer\LaravelReminder\Tests\Fixtures\TestRemindableModel;
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

        // Charger la relation pour éviter les problèmes de lazy loading
        $reminder->setRelation('remindable', $model);

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

        Event::assertDispatched('reminder.processing');
        Event::assertDispatched('reminder.sent');
    }

    public function test_handles_invalid_notification_return_type_with_exception(): void
    {
        // Créer un vrai modèle pour le mock
        $model = new TestRemindableModel();
        $model->name = 'Test Model';
        $model->email = 'test@example.com';
        $model->save();

        $invalidReturnTypes = [
            'array' => ['invalid' => 'array'],
            'string' => 'this is a string',
            'int' => 123,
            'bool' => true,
            'null' => null,
            'object' => new \stdClass(),
        ];

        foreach ($invalidReturnTypes as $typeName => $invalidValue) {
            // Créer un mock partiel qui conserve toutes les méthodes sauf toRemind
            /** @var \Andydefer\LaravelReminder\Tests\Fixtures\TestRemindableModel $mockModel */
            $mockModel = Mockery::mock($model)->makePartial();

            // Pour les types invalides, on doit capturer l'erreur de type PHP
            $mockModel->shouldReceive('toRemind')
                ->once()
                ->andReturn($invalidValue);

            // S'assurer que les autres méthodes fonctionnent normalement
            $mockModel->shouldReceive('notify')->passthru();
            $mockModel->shouldReceive('getTolerance')->passthru();

            $reminder = $this->createReminder($mockModel, Carbon::now()->subMinutes(15));

            $result = $this->service->processReminder($reminder);

            $this->assertFalse($result, "Failed for type: {$typeName}");
            $fresh = $reminder->fresh();
            $this->assertEquals(ReminderStatus::PENDING, $fresh->status);
            $this->assertEquals(1, $fresh->attempts);

            // Vérifier que le message d'erreur indique un problème de type
            // Le message exact de PHP peut varier, on vérifie juste qu'il parle de type
            $this->assertTrue(
                str_contains($fresh->error_message, 'must be of type') ||
                    str_contains($fresh->error_message, 'must return') ||
                    str_contains($fresh->error_message, 'must be an instance of'),
                "Error message for type {$typeName} is: {$fresh->error_message}"
            );
        }
    }

    public function test_handles_exceptions_during_notification_sending(): void
    {
        // Créer un vrai modèle pour le mock
        $model = new TestRemindableModel();
        $model->name = 'Test Model';
        $model->email = 'test@example.com';
        $model->save();

        // Créer un mock partiel
        /** @var \Andydefer\LaravelReminder\Tests\Fixtures\TestRemindableModel $mockModel */
        $mockModel = Mockery::mock($model)->makePartial();

        // toRemind doit retourner une vraie notification
        $notification = new TestNotification($mockModel, Mockery::mock(Reminder::class));
        $mockModel->shouldReceive('toRemind')
            ->once()
            ->andReturn($notification);

        // notify lance une exception
        $mockModel->shouldReceive('notify')
            ->once()
            ->andThrow(new \Exception('Failed to send notification'));

        $reminder = $this->createReminder($mockModel, Carbon::now()->subMinutes(15));

        $result = $this->service->processReminder($reminder);

        $this->assertFalse($result);
        $fresh = $reminder->fresh();
        $this->assertEquals(ReminderStatus::PENDING, $fresh->status);
        $this->assertEquals(1, $fresh->attempts);
        $this->assertStringContainsString('Failed to send notification', $fresh->error_message);
    }

    public function test_respects_max_attempts(): void
    {
        // Créer un vrai modèle pour le mock
        $model = new TestRemindableModel();
        $model->name = 'Test Model';
        $model->email = 'test@example.com';
        $model->save();

        $reminder = $this->createReminder($model, Carbon::now()->subMinutes(15));
        $reminder->attempts = 2;
        $reminder->save();

        // Créer un mock partiel
        /** @var \Andydefer\LaravelReminder\Tests\Fixtures\TestRemindableModel $mockModel */
        $mockModel = Mockery::mock($model)->makePartial();

        // toRemind retourne une notification valide
        $notification = new TestNotification($mockModel, $reminder);
        $mockModel->shouldReceive('toRemind')
            ->once()
            ->andReturn($notification);

        // notify lance toujours une exception
        $mockModel->shouldReceive('notify')
            ->once()
            ->andThrow(new \Exception('Persistent failure'));

        // Remplacer le remindable par notre mock
        $reminder->setRelation('remindable', $mockModel);

        $result = $this->service->processReminder($reminder);

        $this->assertFalse($result);
        $fresh = $reminder->fresh();
        $this->assertEquals(ReminderStatus::FAILED, $fresh->status);
        $this->assertEquals(3, $fresh->attempts);
    }

    /**
     * Test supplémentaire pour vérifier que les notifications sont bien stockées
     */
    public function test_notification_is_stored_in_database(): void
    {
        $model = $this->createTestRemindableModel();
        $reminder = $this->createReminder($model, Carbon::now()->subMinutes(5));

        $result = $this->service->processReminder($reminder);

        $this->assertTrue($result);
        $this->assertEquals(ReminderStatus::SENT, $reminder->fresh()->status);

        // Vérifier que la notification a été créée dans la base de données
        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => TestRemindableModel::class,
            'notifiable_id' => $model->id,
            'type' => TestNotification::class,
        ]);
    }
}
