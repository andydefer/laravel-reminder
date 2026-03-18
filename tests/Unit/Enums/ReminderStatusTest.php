<?php

declare(strict_types=1);

namespace Andydefer\LaravelReminder\Tests\Unit\Enums;

use Andydefer\LaravelReminder\Enums\ReminderStatus;
use Andydefer\LaravelReminder\Tests\TestCase;

/**
 * Unit tests for the ReminderStatus enum.
 *
 * This test suite verifies the functionality of the ReminderStatus enum:
 * - Label generation
 * - Status checking methods
 * - Terminal status detection
 *
 * @package Andydefer\LaravelReminder\Tests\Unit\Enums
 */
class ReminderStatusTest extends TestCase
{
    /**
     * Test label generation for all statuses.
     *
     * @return void
     */
    public function test_generates_correct_labels(): void
    {
        // Assert
        $this->assertEquals('Pending', ReminderStatus::PENDING->label());
        $this->assertEquals('Sent', ReminderStatus::SENT->label());
        $this->assertEquals('Failed', ReminderStatus::FAILED->label());
        $this->assertEquals('Cancelled', ReminderStatus::CANCELLED->label());
    }

    /**
     * Test isPending method.
     *
     * @return void
     */
    public function test_is_pending_detection(): void
    {
        // Assert
        $this->assertTrue(ReminderStatus::PENDING->isPending());
        $this->assertFalse(ReminderStatus::SENT->isPending());
        $this->assertFalse(ReminderStatus::FAILED->isPending());
        $this->assertFalse(ReminderStatus::CANCELLED->isPending());
    }

    /**
     * Test isTerminal method.
     *
     * @return void
     */
    public function test_is_terminal_detection(): void
    {
        // Assert
        $this->assertFalse(ReminderStatus::PENDING->isTerminal());
        $this->assertTrue(ReminderStatus::SENT->isTerminal());
        $this->assertTrue(ReminderStatus::FAILED->isTerminal());
        $this->assertTrue(ReminderStatus::CANCELLED->isTerminal());
    }

    /**
     * Test that all cases are available.
     *
     * @return void
     */
    public function test_has_all_cases(): void
    {
        $cases = ReminderStatus::cases();

        $this->assertCount(4, $cases);
        $this->assertContains(ReminderStatus::PENDING, $cases);
        $this->assertContains(ReminderStatus::SENT, $cases);
        $this->assertContains(ReminderStatus::FAILED, $cases);
        $this->assertContains(ReminderStatus::CANCELLED, $cases);
    }

    /**
     * Test that string values are correct.
     *
     * @return void
     */
    public function test_has_correct_string_values(): void
    {
        // Assert
        $this->assertEquals('pending', ReminderStatus::PENDING->value);
        $this->assertEquals('sent', ReminderStatus::SENT->value);
        $this->assertEquals('failed', ReminderStatus::FAILED->value);
        $this->assertEquals('cancelled', ReminderStatus::CANCELLED->value);
    }
}
