<?php

namespace Wexample\PhpDate\Tests\Class;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Wexample\PhpDate\Class\DateFormatter;
use Wexample\PhpDate\Helper\DateHelper;

class DateFormatterTest extends TestCase
{
    private const WORDING = [
        'en' => [
            DateFormatter::WEEK_KEY => 'Week %week%',
            DateFormatter::WEEK_RANGE_KEY => 'Week %week% · %range%',
        ],
        'fr' => [
            DateFormatter::WEEK_KEY => 'Semaine %week%',
            DateFormatter::WEEK_RANGE_KEY => 'Semaine %week% · %range%',
        ],
    ];

    private function createFormatter(string $locale): DateFormatter
    {
        return new DateFormatter(
            fn (string $key, array $parameters, ?string $callLocale): string => strtr(
                self::WORDING[substr($callLocale ?? $locale, 0, 2)][$key],
                $parameters
            ),
            fn (): string => $locale,
        );
    }

    public static function weekProvider(): array
    {
        return [
            'en week' => ['en', '2026-07-19', DateHelper::DISPLAY_WEEK, 'Week 29'],
            'fr week' => ['fr', '2026-07-19', DateHelper::DISPLAY_WEEK, 'Semaine 29'],
            'en range' => ['en', '2026-07-15', DateHelper::DISPLAY_WEEK_RANGE, 'Week 29 · Jul 13–19'],
            'en_GB range' => ['en_GB', '2026-07-15', DateHelper::DISPLAY_WEEK_RANGE, 'Week 29 · 13–19 Jul'],
            'fr range' => ['fr', '2026-07-15', DateHelper::DISPLAY_WEEK_RANGE, 'Semaine 29 · 13–19 juil.'],
            'fr range across months' => ['fr', '2026-07-29', DateHelper::DISPLAY_WEEK_RANGE, 'Semaine 31 · 27 juil. – 2 août'],
            'en range across years' => ['en', '2025-12-31', DateHelper::DISPLAY_WEEK_RANGE, 'Week 1 · Dec 29, 2025 – Jan 4, 2026'],
        ];
    }

    #[DataProvider('weekProvider')]
    public function testWeek(
        string $locale,
        string $day,
        string $format,
        string $expected,
    ): void {
        $this->assertSame($expected, $this->createFormatter($locale)->format($day, $format));
    }

    public static function timeProvider(): array
    {
        return [
            'en time' => ['en', '2026-07-19 15:04:05', DateHelper::DISPLAY_TIME, "3:04\u{202F}PM"],
            'en time to the second' => ['en', '2026-07-19 15:04:05', DateHelper::DISPLAY_TIME_SECONDS, "3:04:05\u{202F}PM"],
            'fr time' => ['fr', '2026-07-19 15:04:05', DateHelper::DISPLAY_TIME, '15:04'],
            'fr time to the second' => ['fr', '2026-07-19 15:04:05', DateHelper::DISPLAY_TIME_SECONDS, '15:04:05'],
        ];
    }

    // The second shown where it matters — a console, a log — and left out
    // elsewhere: both names, both faces, the same text. ICU sets a narrow
    // no-break space before the day period.
    #[DataProvider('timeProvider')]
    public function testTime(
        string $locale,
        string $moment,
        string $format,
        string $expected,
    ): void {
        $this->assertSame($expected, $this->createFormatter($locale)->format($moment, $format));
    }

    public function testDayRangeKeepsQuotedLiterals(): void
    {
        // Portuguese writes `d 'de' MMM`: the `d` inside the quotes is not the day.
        $this->assertSame(
            '13–19 de jul.',
            $this->createFormatter('pt')->formatDayRange(
                new DateTimeImmutable('2026-07-13'),
                new DateTimeImmutable('2026-07-19')
            )
        );
    }

    public function testOneFormatterAnswersForSeveralLocalesAndFormats(): void
    {
        // The formatters are kept between calls: a second locale, a second
        // format or a raw pattern must not be handed the first one's.
        $formatter = $this->createFormatter('en');
        $moment = '2026-07-19 15:04:05';

        $this->assertSame("3:04\u{202F}PM", $formatter->format($moment, DateHelper::DISPLAY_TIME));
        $this->assertSame('15:04', $formatter->format($moment, DateHelper::DISPLAY_TIME, 'fr'));
        $this->assertSame('2026/07/19', $formatter->format($moment, 'yyyy/MM/dd'));
        $this->assertSame('19.07.2026', $formatter->format($moment, 'dd.MM.yyyy'));
        $this->assertSame('July 2026', $formatter->format($moment, DateHelper::DISPLAY_MONTH_YEAR));
        $this->assertSame('juillet 2026', $formatter->format($moment, DateHelper::DISPLAY_MONTH_YEAR, 'fr'));
        $this->assertSame("3:04\u{202F}PM", $formatter->format($moment, DateHelper::DISPLAY_TIME));
    }

    public function testAFormatterFollowsTheDefaultTimezone(): void
    {
        // A formatter reads the default timezone when it is built: one kept
        // from before a change would print the hour of the former zone.
        $formatter = $this->createFormatter('fr');
        $moment = new DateTimeImmutable('2026-07-19 13:04:05', new \DateTimeZone('UTC'));
        $timezone = date_default_timezone_get();

        try {
            date_default_timezone_set('UTC');
            $this->assertSame('13:04', $formatter->format($moment, DateHelper::DISPLAY_TIME));

            date_default_timezone_set('Europe/Paris');
            $this->assertSame('15:04', $formatter->format($moment, DateHelper::DISPLAY_TIME));
        } finally {
            date_default_timezone_set($timezone);
        }
    }
}
