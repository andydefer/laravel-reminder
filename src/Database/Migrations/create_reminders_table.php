<?php

declare(strict_types=1);

use Andydefer\LaravelReminder\Enums\ReminderStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('reminders', function (Blueprint $table) {
            $table->id();

            // Polymorphic relation
            $table->morphs('remindable');

            // Timing
            $table->dateTime('scheduled_at')->index();
            $table->dateTime('sent_at')->nullable();

            // Status and tracking
            $table->string('status')->default(ReminderStatus::PENDING->value)->index();
            $table->json('metadata')->nullable();
            $table->json('channels')->default(json_encode([])); // Champ JSON pour channels
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->dateTime('last_attempt_at')->nullable();
            $table->text('error_message')->nullable();

            // Timestamps
            $table->timestamps();

            // Indexes utiles
            $table->index(['status', 'scheduled_at']);
            $table->index(['remindable_type', 'remindable_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reminders');
    }
};
