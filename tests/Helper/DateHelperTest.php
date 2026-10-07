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
}
