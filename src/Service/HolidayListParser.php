<?php declare(strict_types=1);

namespace Ms\LeadTime\Service;

/**
 * Parses the holiday list a shop owner types into the plugin settings.
 *
 * One entry per line, anything after the date is a free-text note:
 *
 *   12-25 Christmas Day        (MM-DD: repeats every year)
 *   2027-03-26 Good Friday     (YYYY-MM-DD: that one day only)
 *
 * Fixed yearly holidays use MM-DD. Moving holidays such as Easter change every
 * year, so they are entered as full dates.
 *
 * Lines that are empty or don't start with a valid date are ignored rather than
 * breaking the storefront; they are returned separately so they can be logged.
 */
final class HolidayListParser
{
    /**
     * @return array{dates: list<string>, invalid: list<string>} Dates in Y-m-d, sorted and unique.
     */
    public function parse(string $text, int $fromYear, int $toYear): array
    {
        $dates = [];
        $invalid = [];

        foreach (preg_split('/\R/', $text) ?: [] as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $token = strtok($line, " \t,;");

            if ($token !== false && $this->isValidDate($token, 'Y-m-d')) {
                $dates[] = $token;
                continue;
            }

            if ($token !== false && preg_match('/^\d{2}-\d{2}$/', $token) === 1) {
                $expanded = $this->expandYearly($token, $fromYear, $toYear);

                if ($expanded !== []) {
                    array_push($dates, ...$expanded);
                    continue;
                }
            }

            $invalid[] = $line;
        }

        $dates = array_values(array_unique($dates));
        sort($dates);

        return ['dates' => $dates, 'invalid' => $invalid];
    }

    /**
     * @return list<string>
     */
    private function expandYearly(string $monthDay, int $fromYear, int $toYear): array
    {
        $dates = [];

        for ($year = $fromYear; $year <= $toYear; ++$year) {
            $date = $year . '-' . $monthDay;

            // 02-29 only exists in leap years; other invalid days never do.
            if ($this->isValidDate($date, 'Y-m-d')) {
                $dates[] = $date;
            }
        }

        return $dates;
    }

    private function isValidDate(string $value, string $format): bool
    {
        $date = \DateTimeImmutable::createFromFormat('!' . $format, $value);

        // Round-trip check rejects overflow like 2026-02-30 becoming 2026-03-02.
        return $date !== false && $date->format($format) === $value;
    }
}
