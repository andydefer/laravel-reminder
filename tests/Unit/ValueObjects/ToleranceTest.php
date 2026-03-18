<?php

declare(strict_types=1);

namespace Andydefer\LaravelReminder\Tests\Unit\ValueObjects;

use Andydefer\LaravelReminder\Enums\ToleranceUnit;
use Andydefer\LaravelReminder\ValueObjects\Tolerance;
use Andydefer\LaravelReminder\Tests\TestCase;
use Carbon\Carbon;
use InvalidArgumentException;

/**
 * Unit tests for the Tolerance Value Object.
 *
 * This test suite verifies the functionality of the Tolerance class:
 * - Creation with valid values
 * - Conversion to minutes and seconds
 * - Tolerance window checking
 * - String representation
 * - Validation of negative values
 *
 * @package Andydefer\LaravelReminder\Tests\Unit\ValueObjects
 */
class ToleranceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Freeze time for consistent tests
        Carbon::setTestNow(Carbon::parse('2025-03-20 10:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * Test that a tolerance can be created with valid values.
     *
     * @return void
     */
    public function test_can_create_tolerance_with_valid_values(): void
    {
        // Act
        $tolerance = new Tolerance(30, ToleranceUnit::MINUTE);

        // Assert
        $this->assertInstanceOf(Tolerance::class, $tolerance);
        $this->assertEquals(30, $tolerance->value);
        $this->assertEquals(ToleranceUnit::MINUTE, $tolerance->unit);
    }

    /**
     * Test that creating a tolerance with negative value throws exception.
     *
     * @return void
     */
    public function test_throws_exception_with_negative_value(): void
    {
        // Assert
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Tolerance value cannot be negative');

        // Act
        new Tolerance(-5, ToleranceUnit::MINUTE);
    }

    /**
     * Test conversion to minutes for different units.
     *
     * @return void
     */
    public function test_converts_to_minutes_correctly(): void
    {
        // Arrange & Assert
        $this->assertEquals(525600, (new Tolerance(1, ToleranceUnit::YEAR))->toMinutes());
        $this->assertEquals(43800, (new Tolerance(1, ToleranceUnit::MONTH))->toMinutes());
        $this->assertEquals(10080, (new Tolerance(1, ToleranceUnit::WEEK))->toMinutes());
        $this->assertEquals(1440, (new Tolerance(1, ToleranceUnit::DAY))->toMinutes());
        $this->assertEquals(60, (new Tolerance(1, ToleranceUnit::HOUR))->toMinutes());
        $this->assertEquals(30, (new Tolerance(30, ToleranceUnit::MINUTE))->toMinutes());
        $this->assertEquals(120, (new Tolerance(2, ToleranceUnit::HOUR))->toMinutes());
    }

    /**
     * Test conversion to seconds for different units.
     *
     * @return void
     */
    public function test_converts_to_seconds_correctly(): void
    {
        // Arrange & Assert
        $this->assertEquals(60, (new Tolerance(1, ToleranceUnit::MINUTE))->toSeconds());
        $this->assertEquals(3600, (new Tolerance(1, ToleranceUnit::HOUR))->toSeconds());
        $this->assertEquals(86400, (new Tolerance(1, ToleranceUnit::DAY))->toSeconds());
        $this->assertEquals(1800, (new Tolerance(30, ToleranceUnit::MINUTE))->toSeconds());
    }

    /**
     * Test checking if a datetime is within tolerance window.
     *
     * @return void
     */
    public function test_checks_if_within_tolerance_window(): void
    {
        // Arrange
        $tolerance = new Tolerance(30, ToleranceUnit::MINUTE);
        $now = Carbon::parse('2025-03-20 10:00:00');

        // Assert - within tolerance (15 minutes before)
        $this->assertTrue(
            $tolerance->isWithinWindow(
                Carbon::parse('2025-03-20 09:45:00'),
                $now
            )
        );

        // Assert - within tolerance (20 minutes after)
        $this->assertTrue(
            $tolerance->isWithinWindow(
                Carbon::parse('2025-03-20 10:20:00'),
                $now
            )
        );

        // Assert - exactly at tolerance boundary (30 minutes before)
        $this->assertTrue(
            $tolerance->isWithinWindow(
                Carbon::parse('2025-03-20 09:30:00'),
                $now
            )
        );

        // Assert - outside tolerance (31 minutes before)
        $this->assertFalse(
            $tolerance->isWithinWindow(
                Carbon::parse('2025-03-20 09:29:00'),
                $now
            )
        );

        // Assert - outside tolerance (31 minutes after)
        $this->assertFalse(
            $tolerance->isWithinWindow(
                Carbon::parse('2025-03-20 10:31:00'),
                $now
            )
        );
    }

    /**
     * Test string representation of tolerance.
     *
     * @return void
     */
    public function test_string_representation(): void
    {
        // Arrange & Assert
        $this->assertEquals('30 Minutes', (string) new Tolerance(30, ToleranceUnit::MINUTE));
        $this->assertEquals('2 Hours', (string) new Tolerance(2, ToleranceUnit::HOUR));
        $this->assertEquals('1 Day', (string) new Tolerance(1, ToleranceUnit::DAY));
        $this->assertEquals('3 Days', (string) new Tolerance(3, ToleranceUnit::DAY));
    }

    /**
     * Test tolerance with different units all work correctly.
     *
     * @return void
     */
    public function test_works_with_all_units(): void
    {
        // Arrange
        $now = Carbon::parse('2025-03-20 10:00:00');

        // Create an array of test cases
        $testCases = [
            ['unit' => ToleranceUnit::MINUTE, 'value' => 30],
            ['unit' => ToleranceUnit::HOUR, 'value' => 2],
            ['unit' => ToleranceUnit::DAY, 'value' => 1],
            ['unit' => ToleranceUnit::WEEK, 'value' => 1],
            ['unit' => ToleranceUnit::MONTH, 'value' => 1],
            ['unit' => ToleranceUnit::YEAR, 'value' => 1],
        ];

        foreach ($testCases as $testCase) {
            $tolerance = new Tolerance($testCase['value'], $testCase['unit']);

            // Should be within tolerance for exact match
            $this->assertTrue(
                $tolerance->isWithinWindow(
                    Carbon::parse('2025-03-20 10:00:00'),
                    $now
                ),
                "Failed for unit: {$testCase['unit']->value}"
            );

            // Should be within tolerance for small offset
            $this->assertTrue(
                $tolerance->isWithinWindow(
                    Carbon::parse('2025-03-20 09:55:00'),
                    $now
                ),
                "Failed for unit: {$testCase['unit']->value} with offset"
            );
        }
    }
}
