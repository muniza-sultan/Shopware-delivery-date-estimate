<?php declare(strict_types=1);

namespace Ms\LeadTime\Tests\Service;

use Ms\LeadTime\Service\HolidayListParser;
use PHPUnit\Framework\TestCase;

final class HolidayListParserTest extends TestCase
{
    private HolidayListParser $parser;

    protected function setUp(): void
    {
        $this->parser = new HolidayListParser();
    }

    public function testFullDatesAreKeptAndNotesIgnored(): void
    {
        $result = $this->parser->parse("2027-03-26 Good Friday\n2027-03-29 Easter Monday", 2026, 2027);

        self::assertSame(['2027-03-26', '2027-03-29'], $result['dates']);
        self::assertSame([], $result['invalid']);
    }

    public function testYearlyDatesExpandToEveryYearInRange(): void
    {
        $result = $this->parser->parse('12-25 Christmas Day', 2026, 2027);

        self::assertSame(['2026-12-25', '2027-12-25'], $result['dates']);
    }

    public function testEmptyLinesAndCommentsAreSkipped(): void
    {
        $result = $this->parser->parse("\n# fixed holidays\n\r\n01-01 New Year\n   \n", 2026, 2026);

        self::assertSame(['2026-01-01'], $result['dates']);
        self::assertSame([], $result['invalid']);
    }

    public function testInvalidLinesAreReportedNotFatal(): void
    {
        $result = $this->parser->parse("2026-02-30 nope\n13-01 nope\nChristmas\n12-24", 2026, 2026);

        self::assertSame(['2026-12-24'], $result['dates']);
        self::assertSame(['2026-02-30 nope', '13-01 nope', 'Christmas'], $result['invalid']);
    }

    public function testLeapDayOnlyInLeapYears(): void
    {
        $result = $this->parser->parse('02-29', 2027, 2028);

        self::assertSame(['2028-02-29'], $result['dates']);
        self::assertSame([], $result['invalid']);
    }

    public function testDuplicatesAreRemovedAndResultIsSorted(): void
    {
        $result = $this->parser->parse("12-25\n2026-12-25 again\n2026-01-01", 2026, 2026);

        self::assertSame(['2026-01-01', '2026-12-25'], $result['dates']);
    }
}
