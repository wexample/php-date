<?php

namespace Wexample\PhpDate\Tests\Helper;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Wexample\PhpDate\Helper\DateHelper;

class DateHelperTest extends TestCase
{
    public static function weekProvider(): array
    {
        return [
            'mid-year sunday' => ['2026-07-19', '2026-W29', '2026-07-13', '2026-07-19'],
            'monday' => ['2026-07-13', '2026-W29', '2026-07-13', '2026-07-19'],
            // Early January in the last week of the year before.
            'january in week 53' => ['2021-01-02', '2020-W53', '2020-12-28', '2021-01-03'],
            // Late December in the first week of the year after.
            'december in week 1' => ['2025-12-30', '2026-W01', '2025-12-29', '2026-01-04'],
        ];
    }

    #[DataProvider('weekProvider')]
    public function testWeek(
        string $day,
        string $weekKey,
        string $monday,
        string $sunday,
    ): void {
        $date = new DateTimeImmutable($day.' 15:30');

        $this->assertSame($weekKey, DateHelper::getWeekKey($date));
        $this->assertSame((int) substr($weekKey, 6), DateHelper::getWeekNumber($date));
        $this->assertSame((int) substr($weekKey, 0, 4), DateHelper::getWeekYear($date));
        $this->assertSame($monday.' 00:00:00', DateHelper::startOfWeek($date)->format('Y-m-d H:i:s'));
        $this->assertSame($sunday.' 23:59:59', DateHelper::endOfWeek($date)->format('Y-m-d H:i:s'));
        $this->assertSame($monday.' 00:00:00', DateHelper::buildFromWeekKey($weekKey)->format('Y-m-d H:i:s'));
    }

    public function testBuildFromWeekKeyRejectsWhatIsNotAWeek(): void
    {
        $this->assertNull(DateHelper::buildFromWeekKey('2026-29'));
        $this->assertNull(DateHelper::buildFromWeekKey('2026-W00'));
        // 2026 has 53 weeks, 2025 only 52.
        $this->assertNotNull(DateHelper::buildFromWeekKey('2026-W53'));
        $this->assertNull(DateHelper::buildFromWeekKey('2025-W53'));
    }

    public function testQueryStringTakesAWeek(): void
    {
        $this->assertSame(
            '2026-07-13 00:00:00',
            DateHelper::buildFromQueryStringDate('2026-W29')->format('Y-m-d H:i:s')
        );
        $this->assertNull(DateHelper::buildFromQueryStringDate('2025-W53'));
        $this->assertSame(
            '2026-07-01 00:00:00',
            DateHelper::buildFromQueryStringDate('2026-07')->format('Y-m-d H:i:s')
        );
    }

    public static function queryStringProvider(): array
    {
        return [
            // Read on the 29th to the 31st, a month without its day used to
            // take today's and roll over: February into March.
            'february' => ['2023-02', '2023-02-01 00:00:00'],
            'leap february' => ['2024-02', '2024-02-01 00:00:00'],
            'thirty days' => ['2024-04', '2024-04-01 00:00:00'],
            'year' => ['2024', '2024-01-01 00:00:00'],
            'day' => ['2024-02-29', '2024-02-29 00:00:00'],
            'hour' => ['2024-02-29 13', '2024-02-29 13:00:00'],
            'minute' => ['2024-02-29 13:45', '2024-02-29 13:45:00'],
            'second' => ['2024-02-29 13:45:12', '2024-02-29 13:45:12'],
        ];
    }

    #[DataProvider('queryStringProvider')]
    public function testQueryStringDateLeavesOutWhatItDoesNotSay(string $value, string $expected): void
    {
        $date = DateHelper::buildFromQueryStringDate($value);

        $this->assertNotNull($date);
        $this->assertSame($expected, $date->format('Y-m-d H:i:s'));
        $this->assertSame('000000', $date->format('u'));
    }

    public function testQueryStringDateRejectsWhatIsNoDate(): void
    {
        $this->assertNull(DateHelper::buildFromQueryStringDate('2023-02-30'));
        $this->assertNull(DateHelper::buildFromQueryStringDate('2024-13'));
        $this->assertNull(DateHelper::buildFromQueryStringDate(''));
        $this->assertNull(DateHelper::buildFromQueryStringDate(null));
    }
}
