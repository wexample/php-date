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
}
