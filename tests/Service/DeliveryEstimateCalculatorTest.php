<?php declare(strict_types=1);

namespace Ms\LeadTime\Tests\Service;

use Ms\LeadTime\Service\DeliveryEstimate;
use Ms\LeadTime\Service\DeliveryEstimateCalculator;
use PHPUnit\Framework\TestCase;

/**
 * Reference week (November 2026):
 * Tue 10 | Wed 11 | Thu 12 | Fri 13 | Sat 14 | Sun 15 | Mon 16 | Tue 17 | Wed 18
 */
final class DeliveryEstimateCalculatorTest extends TestCase
{
    private DeliveryEstimateCalculator $calculator;

    protected function setUp(): void
    {
        $this->calculator = new DeliveryEstimateCalculator();
    }

    public function testOrderBeforeCutoffShipsTodayAndSkipsWeekend(): void
    {
        $estimate = $this->calc(2, 5, 'day', '2026-11-10 10:00');

        $this->assertDates('2026-11-12', '2026-11-17', $estimate);
        self::assertSame('2026-11-10 14:00', $estimate->orderBy?->format('Y-m-d H:i'));
    }

    public function testCalendarDaysReproduceCoreBehaviour(): void
    {
        // What Shopware core shows in the cart: 12/11 - 15/11, a Sunday.
        $estimate = $this->calc(2, 5, 'day', '2026-11-10 10:00', businessDaysOnly: false);

        $this->assertDates('2026-11-12', '2026-11-15', $estimate);
    }

    public function testOrderAfterCutoffShipsNextWorkingDay(): void
    {
        $estimate = $this->calc(2, 5, 'day', '2026-11-10 15:00');

        $this->assertDates('2026-11-13', '2026-11-18', $estimate);
        self::assertNull($estimate->orderBy);
    }

    public function testOrderExactlyAtCutoffCountsAsLate(): void
    {
        $estimate = $this->calc(1, 1, 'day', '2026-11-10 14:00');

        $this->assertDates('2026-11-12', '2026-11-12', $estimate);
        self::assertNull($estimate->orderBy);
    }

    public function testFridayAfterCutoffShipsMonday(): void
    {
        $estimate = $this->calc(1, 2, 'day', '2026-11-13 15:00');

        $this->assertDates('2026-11-17', '2026-11-18', $estimate);
    }

    public function testWeekendOrderShipsMondayWithoutCountdown(): void
    {
        $estimate = $this->calc(1, 1, 'day', '2026-11-14 10:00');

        $this->assertDates('2026-11-17', '2026-11-17', $estimate);
        self::assertNull($estimate->orderBy);
    }

    public function testHolidayIsSkippedWhileCounting(): void
    {
        $estimate = $this->calc(1, 1, 'day', '2026-11-10 10:00', holidays: ['2026-11-11']);

        $this->assertDates('2026-11-12', '2026-11-12', $estimate);
    }

    public function testOrderOnHolidayShipsNextWorkingDay(): void
    {
        $estimate = $this->calc(1, 1, 'day', '2026-11-11 10:00', holidays: ['2026-11-11']);

        $this->assertDates('2026-11-13', '2026-11-13', $estimate);
        self::assertNull($estimate->orderBy);
    }

    public function testChristmasAndNewYear(): void
    {
        // Wed 23 Dec after cutoff. 24-27 are holidays or weekend, so dispatch is Mon 28.
        // +2 working days: Tue 29, Wed 30. +3: 31 Dec and 1 Jan are holidays, then the weekend, so Mon 4 Jan.
        $estimate = $this->calc(2, 3, 'day', '2026-12-23 15:00', holidays: [
            '2026-12-24', '2026-12-25', '2026-12-26', '2026-12-31', '2027-01-01',
        ]);

        $this->assertDates('2026-12-30', '2027-01-04', $estimate);
    }

    public function testWeeksCountAsFiveBusinessDays(): void
    {
        $estimate = $this->calc(1, 2, 'week', '2026-11-10 10:00');

        $this->assertDates('2026-11-17', '2026-11-24', $estimate);
    }

    public function testHoursRoundUpToWholeDays(): void
    {
        $estimate = $this->calc(24, 48, 'hour', '2026-11-10 10:00');

        $this->assertDates('2026-11-11', '2026-11-12', $estimate);
    }

    public function testInvalidRangeIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->calc(5, 2, 'day', '2026-11-10 10:00');
    }

    public function testUnknownUnitIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->calc(1, 2, 'fortnight', '2026-11-10 10:00');
    }

    /**
     * @param list<string> $holidays
     */
    private function calc(
        int $min,
        int $max,
        string $unit,
        string $now,
        int $cutoffHour = 14,
        bool $businessDaysOnly = true,
        array $holidays = [],
    ): DeliveryEstimate {
        return $this->calculator->calculate(
            $min,
            $max,
            $unit,
            new \DateTimeImmutable($now, new \DateTimeZone('Europe/Berlin')),
            $cutoffHour,
            $businessDaysOnly,
            $holidays,
        );
    }

    private function assertDates(string $earliest, string $latest, DeliveryEstimate $estimate): void
    {
        self::assertSame($earliest, $estimate->earliest->format('Y-m-d'), 'earliest');
        self::assertSame($latest, $estimate->latest->format('Y-m-d'), 'latest');
    }
}
