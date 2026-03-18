<?php

declare(strict_types=1);

namespace Andydefer\LaravelReminder\Tests\Unit\Enums;

use Andydefer\LaravelReminder\Enums\ToleranceUnit;
use Andydefer\LaravelReminder\Tests\TestCase;

/**
 * Unit tests for the ToleranceUnit enum.
 *
 * This test suite verifies the functionality of the ToleranceUnit enum:
 * - Conversion to minutes
 * - Conversion to seconds
 * - Label generation
 *
 * @package Andydefer\LaravelReminder\Tests\Unit\Enums
 */
class ToleranceUnitTest extends TestCase
{
    /**
     * Test conversion to minutes for all units.
     *
     * @return void
     */
    public function test_converts_to_minutes_correctly(): void
    {
        // Assert
        $this->assertEquals(525600, ToleranceUnit::YEAR->toMinutes());
        $this->assertEquals(43800, ToleranceUnit::MONTH->toMinutes());
        $this->assertEquals(10080, ToleranceUnit::WEEK->toMinutes());
        $this->assertEquals(1440, ToleranceUnit::DAY->toMinutes());
        $this->assertEquals(60, ToleranceUnit::HOUR->toMinutes());
        $this->assertEquals(1, ToleranceUnit::MINUTE->toMinutes());
    }

    /**
     * Test conversion to seconds for all units.
     *
     * @return void
     */
    public function test_converts_to_seconds_correctly(): void
    {
        // Assert
        $this->assertEquals(525600 * 60, ToleranceUnit::YEAR->toSeconds());
        $this->assertEquals(43800 * 60, ToleranceUnit::MONTH->toSeconds());
        $this->assertEquals(10080 * 60, ToleranceUnit::WEEK->toSeconds());
        $this->assertEquals(1440 * 60, ToleranceUnit::DAY->toSeconds());
        $this->assertEquals(60 * 60, ToleranceUnit::HOUR->toSeconds());
        $this->assertEquals(60, ToleranceUnit::MINUTE->toSeconds());
    }

    /**
     * Test that toMinutes and toSeconds are consistent.
     *
     * @return void
     */
    public function test_minutes_and_seconds_are_consistent(): void
    {
        foreach (ToleranceUnit::cases() as $unit) {
            $this->assertEquals(
                $unit->toMinutes() * 60,
                $unit->toSeconds()
            );
        }
    }

    /**
     * Test label generation for all units.
     *
     * @return void
     */
    public function test_generates_correct_labels(): void
    {
        // Assert
        $this->assertEquals('Year', ToleranceUnit::YEAR->label());
        $this->assertEquals('Month', ToleranceUnit::MONTH->label());
        $this->assertEquals('Week', ToleranceUnit::WEEK->label());
        $this->assertEquals('Day', ToleranceUnit::DAY->label());
        $this->assertEquals('Hour', ToleranceUnit::HOUR->label());
        $this->assertEquals('Minute', ToleranceUnit::MINUTE->label());
    }

    /**
     * Test that all cases are available.
     *
     * @return void
     */
    public function test_has_all_cases(): void
    {
        $cases = ToleranceUnit::cases();

        $this->assertCount(6, $cases);
        $this->assertContains(ToleranceUnit::YEAR, $cases);
        $this->assertContains(ToleranceUnit::MONTH, $cases);
        $this->assertContains(ToleranceUnit::WEEK, $cases);
        $this->assertContains(ToleranceUnit::DAY, $cases);
        $this->assertContains(ToleranceUnit::HOUR, $cases);
        $this->assertContains(ToleranceUnit::MINUTE, $cases);
    }
}
