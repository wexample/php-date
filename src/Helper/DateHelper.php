<?php

namespace Wexample\PhpDate\Helper;

use DateInterval;
use DatePeriod;
use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use IntlDateFormatter;

class DateHelper
{
    public const DATE_PATTERN_PART_YEAR_FULL = 'Y';
    public const DATE_PATTERN_PART_MONTH_FULL = 'm';
    public const DATE_PATTERN_PART_DAY_FULL = 'd';
    public const DATE_PATTERN_PART_HOURS_FULL = 'H';
    public const DATE_PATTERN_PART_MINUTES_FULL = 'i';
    public const DATE_PATTERN_PART_SECONDS_FULL = 's';
    public const DATE_PATTERN_DAY_DEFAULT =
        self::DATE_PATTERN_PART_YEAR_FULL.'-'.
        self::DATE_PATTERN_PART_MONTH_FULL.'-'.
        self::DATE_PATTERN_PART_DAY_FULL;
    public const DATE_PATTERN_DAY_REVERTED =
        self::DATE_PATTERN_PART_YEAR_FULL.'-'.
        self::DATE_PATTERN_PART_DAY_FULL.'-'.
        self::DATE_PATTERN_PART_MONTH_FULL;
    public const DATE_PATTERN_YMD_FR =
        self::DATE_PATTERN_PART_DAY_FULL.'/'.
        self::DATE_PATTERN_PART_MONTH_FULL.'/'.
        self::DATE_PATTERN_PART_YEAR_FULL;
    public const DATE_PATTERN_MICROTIME_DEFAULT = self::DATE_PATTERN_TIME_DEFAULT.'.u';
    public const TIME_PATTERN_SECOND_DEFAULT = self::DATE_PATTERN_PART_HOURS_FULL.':'.self::DATE_PATTERN_PART_MINUTES_FULL.':'.self::DATE_PATTERN_PART_SECONDS_FULL;
    public const DATE_PATTERN_TIME_ZULU = self::DATE_PATTERN_DAY_DEFAULT.'\T'.self::TIME_PATTERN_SECOND_DEFAULT.'p';
    public const DATE_PATTERN_TIME_DEFAULT = self::DATE_PATTERN_DAY_DEFAULT.' '.self::TIME_PATTERN_SECOND_DEFAULT;
    public const DATE_PATTERN_TIME_REVERTED = self::DATE_PATTERN_DAY_REVERTED.' '.self::TIME_PATTERN_SECOND_DEFAULT;
    public const DATE_PATTERN_ISO08601 = self::DATE_PATTERN_DAY_DEFAULT.'\T'.self::TIME_PATTERN_SECOND_DEFAULT;
    // @see https://unicode-org.github.io/icu/userguide/format_parse/datetime/
    public const INTL_DATE_FORMATTER_MONTH_FULL = 'MMMM';
    public const INTL_DATE_FORMATTER_YEAR_FULL = 'YYYY';
    public const INTL_DATE_PATTERN_MONTH_AND_YEAR_FULL =
        self::INTL_DATE_FORMATTER_MONTH_FULL
        .' '.self::INTL_DATE_FORMATTER_YEAR_FULL;
    public const QUERY_STRING_DATE_FORMATS = [
        self::DATE_PATTERN_TIME_DEFAULT,
        'Y-m-d H:i',
        'Y-m-d H',
        'Y-m-d',
        'Y-m',
        self::DATE_PATTERN_PART_YEAR_FULL,
    ];

    // The vocabulary a display asks for, shared with the JavaScript side so that a
    // format named in a template means the same thing once the browser takes over.
    public const DISPLAY_TIME = 'time';
    public const DISPLAY_DATE = 'date';
    public const DISPLAY_DATE_SHORT = 'date_short';
    public const DISPLAY_DATE_LONG = 'date_long';
    public const DISPLAY_DATE_TIME = 'date_time';
    public const DISPLAY_DATE_TIME_SHORT = 'date_time_short';
    public const DISPLAY_DATE_TIME_FULL = 'date_time_full';
    public const DISPLAY_MONTH_YEAR = 'month_year';
    public const DISPLAY_WEEK = 'week';
    public const DISPLAY_WEEK_RANGE = 'week_range';
    public const DISPLAY_RELATIVE = 'relative';
    public const DISPLAY_AUTO = 'auto';

    public const RELATIVE_UNIT_NOW = 'now';
    public const RELATIVE_UNIT_MINUTE = 'minute';
    public const RELATIVE_UNIT_HOUR = 'hour';
    public const RELATIVE_UNIT_DAY = 'day';
    public const RELATIVE_UNIT_WEEK = 'week';
    public const RELATIVE_UNIT_MONTH = 'month';
    public const RELATIVE_UNIT_YEAR = 'year';

    public const RELATIVE_UNIT_SECONDS = [
        self::RELATIVE_UNIT_MINUTE => 60,
        self::RELATIVE_UNIT_HOUR => 3600,
        self::RELATIVE_UNIT_DAY => 86400,
        self::RELATIVE_UNIT_WEEK => 604800,
        // Averaged, so that "3 months ago" does not shift with the length of the
        // months it happens to span.
        self::RELATIVE_UNIT_MONTH => 2629800,
        self::RELATIVE_UNIT_YEAR => 31557600,
    ];

    // Read as: below this many seconds of distance, count in that unit. Past the
    // last rung, count in years.
    public const RELATIVE_LADDER = [
        [45, self::RELATIVE_UNIT_NOW],
        [3600, self::RELATIVE_UNIT_MINUTE],
        [86400, self::RELATIVE_UNIT_HOUR],
        [604800, self::RELATIVE_UNIT_DAY],
        [2629800, self::RELATIVE_UNIT_WEEK],
        [31557600, self::RELATIVE_UNIT_MONTH],
    ];

    // Where `auto` stops counting backwards and shows a calendar date instead.
    public const RELATIVE_AUTO_LIMIT_SECONDS = 604800;

    // An ISO 8601 week, as `<input type="week">` posts it: `2026-W29`.
    public const WEEK_KEY_REGEX = '/^(\d{4})-W(\d{2})$/';

    public static function parse(
        DateTimeInterface|string|int|null $value
    ): ?DateTimeImmutable {
        if (null === $value || '' === $value) {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($value);
        }

        // A bare number is a Unix timestamp, in seconds or in milliseconds.
        if (is_int($value) || ctype_digit((string) $value)) {
            $stamp = (int) $value;

            return new DateTimeImmutable('@'.($stamp > 99999999999 ? intdiv($stamp, 1000) : $stamp));
        }

        return new DateTimeImmutable($value);
    }

    /**
     * Which unit a distance in time should be counted in, and how many of them.
     *
     * @return array{unit: string, count: int, past: bool}
     */
    public static function relativeDiff(int $seconds): array
    {
        $elapsed = abs($seconds);
        $past = $seconds >= 0;

        foreach (self::RELATIVE_LADDER as [$limit, $unit]) {
            if ($elapsed < $limit) {
                return [
                    'unit' => $unit,
                    'count' => self::RELATIVE_UNIT_NOW === $unit
                        ? 0
                        : max(1, (int) round($elapsed / self::RELATIVE_UNIT_SECONDS[$unit])),
                    'past' => $past,
                ];
            }
        }

        return [
            'unit' => self::RELATIVE_UNIT_YEAR,
            'count' => max(1, (int) round($elapsed / self::RELATIVE_UNIT_SECONDS[self::RELATIVE_UNIT_YEAR])),
            'past' => $past,
        ];
    }

    public static function generateFromTimestamp(int $timestamp): DateTimeInterface
    {
        $date = new DateTime();
        $date->setTimestamp($timestamp);

        return $date;
    }

    public static function buildFromYear(int $year): DateTimeInterface
    {
        return DateTime::createFromFormat(
            self::DATE_PATTERN_PART_YEAR_FULL,
            $year
        );
    }

    public static function buildFromTimestamp(int $timestamp): DateTimeInterface
    {
        return DateTime::createFromFormat('U', $timestamp);
    }

    public static function forEachMonthInYear(
        DateTimeInterface $dateYear,
        callable $callback
    ): void {
        $interval = new DateInterval('P1M');
        $dateStart = (clone $dateYear)->modify(
            'first day of january this year'
        );
        $dateEnd = (clone $dateYear)->modify('last day of december this year');
        $period = new DatePeriod($dateStart, $interval, $dateEnd);

        foreach ($period as $dateMonth) {
            $callback($dateMonth);
        }
    }

    public static function getMonthKey(DateTimeInterface $dateTime): string
    {
        return $dateTime->format('Y-m');
    }

    // Weeks are ISO 8601 whatever the locale: Monday first, week 1 holding the
    // first Thursday of the year. ICU's `w` follows the locale instead, and an
    // English locale would then number from Sunday, which is not the week a
    // planning counts in.

    public static function getWeekNumber(DateTimeInterface $dateTime): int
    {
        return (int) $dateTime->format('W');
    }

    /**
     * The year the week belongs to, which differs from the calendar year for the
     * days of late December and early January that fall in a neighbouring week.
     */
    public static function getWeekYear(DateTimeInterface $dateTime): int
    {
        return (int) $dateTime->format('o');
    }

    public static function getWeekKey(DateTimeInterface $dateTime): string
    {
        return $dateTime->format('o-\WW');
    }

    /**
     * The Monday of a `2026-W29` week, at midnight, or null when the key is not a
     * week that exists.
     */
    public static function buildFromWeekKey(string $weekKey): ?DateTimeImmutable
    {
        if (! preg_match(self::WEEK_KEY_REGEX, $weekKey, $matches)) {
            return null;
        }

        $monday = (new DateTimeImmutable())->setISODate((int) $matches[1], (int) $matches[2])->setTime(0, 0);

        // `setISODate()` rolls week 53 of a 52-week year into the next one.
        return self::getWeekKey($monday) === $weekKey ? $monday : null;
    }

    public static function startOfWeek(DateTimeInterface $date): DateTimeImmutable
    {
        return DateTimeImmutable::createFromInterface($date)
            ->setISODate(self::getWeekYear($date), self::getWeekNumber($date))
            ->setTime(0, 0);
    }

    public static function endOfWeek(DateTimeInterface $date): DateTimeImmutable
    {
        return self::startOfWeek($date)->modify('+6 days')->setTime(23, 59, 59);
    }

    public static function isInMonth(
        DateTimeInterface $dateSearch,
        DateTimeInterface $dateMonth
    ): bool {
        $dateStart = self::startOfMonth($dateMonth);
        $dateEnd = self::endOfMonth($dateMonth);

        return $dateStart <= $dateSearch && $dateSearch <= $dateEnd;
    }

    public static function startOfMonth(
        DateTimeInterface $date
    ): DateTimeInterface {
        $date = static::startOfDay($date);

        return $date->modify('first day of this month');
    }

    public static function startOfDay(
        DateTimeInterface $date
    ): DateTimeInterface {
        return (clone $date)->setTime(0, 0);
    }

    public static function endOfMonth(
        DateTimeInterface $date
    ): DateTimeInterface {
        $date = static::endOfDay($date);

        return $date->modify('last day of this month');
    }

    public static function endOfDay(
        DateTimeInterface $date
    ): DateTimeInterface {
        return (clone $date)->setTime(23, 59, 59);
    }

    public static function endOfYear(DateTimeInterface $date): DateTimeInterface
    {
        return self::endOfMonth((clone $date)->modify('last day of december'));
    }

    public static function startOfYear(DateTimeInterface $date): DateTimeInterface
    {
        return self::startOfMonth((clone $date)->modify('first day of january'));
    }

    public static function interfaceToDateTime(
        DateTimeInterface $interface
    ): DateTimeInterface {
        $dateTime = new DateTime();

        return $dateTime->setTimestamp($interface->getTimestamp());
    }

    public static function dayOfMonth(
        DateTimeInterface $dateTime,
        int $dayOfMonth
    ): DateTimeInterface {
        return (clone $dateTime)
            ->setDate(
                $dateTime->format(
                    self::DATE_PATTERN_PART_YEAR_FULL
                ),
                $dateTime->format(
                    self::DATE_PATTERN_PART_MONTH_FULL
                ),
                $dayOfMonth,
            );
    }

    public static function getDayInt(DateTimeInterface $dateTime): int
    {
        return (int) $dateTime->format('j');
    }

    public static function translateDate(
        DateTimeInterface $dateTime,
        string $format
    ): string {
        return IntlDateFormatter::formatObject(
            $dateTime,
            $format
        );
    }

    public static function getNextYearDateTime(): DateTimeInterface
    {
        // First january of next year.
        $dateAccounting = new DateTime();
        $dateAccounting->modify('+1 year');

        return self::startOfYear($dateAccounting);
    }

    public static function getCurrentYearDate(): DateTimeInterface
    {
        return DateTime::createFromFormat(
            self::DATE_PATTERN_DAY_DEFAULT,
            self::getCurrentYearInt().'-01-01'
        );
    }

    public static function getCurrentYearInt(): int
    {
        return (int) (new DateTime())->format(self::DATE_PATTERN_PART_YEAR_FULL);
    }

    public static function now(): DateTimeInterface
    {
        return new DateTime();
    }

    public static function buildFromQueryStringDate(?string $value): ?DateTimeInterface
    {
        if ($value && preg_match(self::WEEK_KEY_REGEX, $value)) {
            $monday = self::buildFromWeekKey($value);

            return $monday ? DateTime::createFromImmutable($monday) : null;
        }

        if ($value) {
            foreach (self::QUERY_STRING_DATE_FORMATS as $format) {
                $dateTime = DateTime::createFromFormat($format, $value);
                if (false !== $dateTime
                    && $dateTime->format($format) == $value) {
                    // Check if day is missing
                    if (! str_contains($format, self::DATE_PATTERN_PART_DAY_FULL)) {
                        $dateTime->setDate(
                            $dateTime->format(self::DATE_PATTERN_PART_YEAR_FULL),
                            $dateTime->format(self::DATE_PATTERN_PART_MONTH_FULL),
                            1
                        );
                    }
                    // Check if month is missing
                    if (! str_contains($format, self::DATE_PATTERN_PART_MONTH_FULL)) {
                        $dateTime->setDate(
                            $dateTime->format(self::DATE_PATTERN_PART_YEAR_FULL),
                            1,
                            1
                        );
                    }
                    // Check if time is missing
                    if (! str_contains($format, self::DATE_PATTERN_PART_HOURS_FULL)) {
                        $dateTime->setTime(0, 0);
                    } elseif (! str_contains($format, self::DATE_PATTERN_PART_MINUTES_FULL)) {
                        // if the format contains hour but not minutes
                        $dateTime->setTime(
                            $dateTime->format(self::DATE_PATTERN_PART_HOURS_FULL),
                            0
                        );
                    } elseif (! str_contains($format, self::DATE_PATTERN_PART_SECONDS_FULL)) {
                        // if the format contains hour and minutes but not seconds
                        $dateTime->setTime(
                            $dateTime->format(self::DATE_PATTERN_PART_HOURS_FULL),
                            $dateTime->format(self::DATE_PATTERN_PART_MINUTES_FULL)
                        );
                    }

                    return $dateTime;
                }
            }
        }

        return null;
    }
}
