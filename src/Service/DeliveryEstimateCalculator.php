<?php declare(strict_types=1);

namespace Ms\LeadTime\Service;

/**
 * Turns a delivery time ("2-5 days") into concrete delivery dates.
 *
 * Pure logic on purpose: no Shopware classes, no config, no clock. Callers pass
 * in "now" (already in the shop's time zone) and the settings, which makes every
 * rule easy to unit test with fixed dates.
 *
 * Rules:
 * - Orders before the cutoff hour on a working day are dispatched today,
 *   all others on the next working day.
 * - Min/max are counted in working days from the dispatch day.
 * - A working day is any day that is not a holiday and, when $businessDaysOnly
 *   is true, not a Saturday or Sunday.
 */
final class DeliveryEstimateCalculator
{
    // Same unit values as Shopware's DeliveryTimeEntity.
    public const UNIT_HOUR = 'hour';
    public const UNIT_DAY = 'day';
    public const UNIT_WEEK = 'week';
    public const UNIT_MONTH = 'month';
    public const UNIT_YEAR = 'year';

    /**
     * @param list<string> $holidays Dates in Y-m-d format, e.g. "2026-12-25".
     */
    public function calculate(
        int $min,
        int $max,
        string $unit,
        \DateTimeImmutable $now,
        int $cutoffHour,
        bool $businessDaysOnly,
        array $holidays = [],
    ): DeliveryEstimate {
        if ($min < 0 || $max < $min) {
            throw new \InvalidArgumentException(sprintf('Invalid delivery time range %d-%d.', $min, $max));
        }

        $holidaySet = array_fill_keys($holidays, true);
        $today = $now->setTime(0, 0);
        $beforeCutoff = (int) $now->format('G') < $cutoffHour;

        if ($beforeCutoff && $this->isWorkingDay($today, $businessDaysOnly, $holidaySet)) {
            $dispatchDay = $today;
            $orderBy = $today->setTime($cutoffHour, 0);
        } else {
            $dispatchDay = $this->addWorkingDays($today, 1, $businessDaysOnly, $holidaySet);
            $orderBy = null;
        }

        return new DeliveryEstimate(
            $this->addWorkingDays($dispatchDay, $this->toDays($min, $unit, $businessDaysOnly), $businessDaysOnly, $holidaySet),
            $this->addWorkingDays($dispatchDay, $this->toDays($max, $unit, $businessDaysOnly), $businessDaysOnly, $holidaySet),
            $orderBy,
        );
    }

    /**
     * Converts a delivery time value to a number of (working) days.
     * Weeks, months and years are approximations, documented in the README.
     */
    private function toDays(int $value, string $unit, bool $businessDaysOnly): int
    {
        return match ($unit) {
            self::UNIT_HOUR => intdiv($value + 23, 24), // round up to whole days
            self::UNIT_DAY => $value,
            self::UNIT_WEEK => $value * ($businessDaysOnly ? 5 : 7),
            self::UNIT_MONTH => $value * ($businessDaysOnly ? 20 : 30),
            self::UNIT_YEAR => $value * ($businessDaysOnly ? 250 : 365),
            default => throw new \InvalidArgumentException(sprintf('Unknown delivery time unit "%s".', $unit)),
        };
    }

    /**
     * @param array<string, true> $holidaySet
     */
    private function addWorkingDays(\DateTimeImmutable $day, int $count, bool $businessDaysOnly, array $holidaySet): \DateTimeImmutable
    {
        while ($count > 0) {
            $day = $day->modify('+1 day');

            if ($this->isWorkingDay($day, $businessDaysOnly, $holidaySet)) {
                --$count;
            }
        }

        return $day;
    }

    /**
     * @param array<string, true> $holidaySet
     */
    private function isWorkingDay(\DateTimeImmutable $day, bool $businessDaysOnly, array $holidaySet): bool
    {
        if (isset($holidaySet[$day->format('Y-m-d')])) {
            return false;
        }

        // N: 1 (Monday) to 7 (Sunday)
        return !$businessDaysOnly || (int) $day->format('N') <= 5;
    }
}
