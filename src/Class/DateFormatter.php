<?php

namespace Wexample\PhpDate\Class;

use Closure;
use DateTimeImmutable;
use DateTimeInterface;
use IntlDateFormatter;
use IntlDatePatternGenerator;
use Wexample\PhpDate\Helper\DateHelper;

/**
 * Turns a moment into the text a reader sees.
 *
 * The counterpart of the JavaScript formatter of the same name: both read the same
 * format names, the same relative ladder and the same translation keys, so a date
 * printed on the server and the same date redrawn by the browser a minute later say
 * the same thing. Absolute formats go through ICU date and time styles rather than
 * hand-written patterns, because that is the one notion `IntlDateFormatter` and the
 * browser's `Intl.DateTimeFormat` express identically.
 *
 * Wording of the relative forms is left to a translator handed in at construction,
 * so that the framework around it decides where the catalogue lives.
 */
class DateFormatter
{
    public const RELATIVE_KEY_NOW = 'date.relative.now';
    public const RELATIVE_KEY_PREFIX = 'date.relative.';
    // `%week%`, plus `%range%` for the range form: « Week %week% · %range% ».
    public const WEEK_KEY = 'date.week.label';
    public const WEEK_RANGE_KEY = 'date.week.range';

    private const INTL_STYLES = [
        DateHelper::DISPLAY_TIME => [IntlDateFormatter::NONE, IntlDateFormatter::SHORT],
        DateHelper::DISPLAY_DATE => [IntlDateFormatter::MEDIUM, IntlDateFormatter::NONE],
        DateHelper::DISPLAY_DATE_SHORT => [IntlDateFormatter::SHORT, IntlDateFormatter::NONE],
        DateHelper::DISPLAY_DATE_LONG => [IntlDateFormatter::LONG, IntlDateFormatter::NONE],
        DateHelper::DISPLAY_DATE_TIME => [IntlDateFormatter::MEDIUM, IntlDateFormatter::SHORT],
        DateHelper::DISPLAY_DATE_TIME_SHORT => [IntlDateFormatter::SHORT, IntlDateFormatter::SHORT],
        DateHelper::DISPLAY_DATE_TIME_FULL => [IntlDateFormatter::FULL, IntlDateFormatter::MEDIUM],
    ];

    // Formats no pair of styles expresses, given as ICU skeletons: the field set
    // is ours, the order and the separators are the locale's.
    private const INTL_SKELETONS = [
        DateHelper::DISPLAY_MONTH_YEAR => 'MMMM y',
    ];

    // The day and month of either end of a range, the year joining them only when
    // the range crosses one.
    private const RANGE_SKELETON = 'dMMM';
    private const RANGE_SKELETON_YEAR = 'dMMMy';
    private const RANGE_SEPARATOR_DAYS = '–';
    private const RANGE_SEPARATOR_DATES = ' – ';

    private readonly Closure $translate;
    private readonly Closure $resolveLocale;

    /**
     * @param callable $translate `fn(string $key, array $parameters, ?string $locale): string`,
     *                            the parameters holding `%count%` where the wording counts
     * @param callable $resolveLocale `fn(): string`, asked whenever a call names no locale
     */
    public function __construct(
        callable $translate,
        callable $resolveLocale,
    ) {
        $this->translate = $translate(...);
        $this->resolveLocale = $resolveLocale(...);
    }

    /**
     * @param string $format One of the `DateHelper::DISPLAY_*` names, or a raw ICU
     *                       pattern for a shape the named formats do not cover
     */
    public function format(
        DateTimeInterface|string|int|null $value,
        string $format = DateHelper::DISPLAY_AUTO,
        ?string $locale = null,
        ?DateTimeInterface $now = null,
    ): string {
        $date = DateHelper::parse($value);

        if (null === $date) {
            return '';
        }

        $now = DateHelper::parse($now) ?? new DateTimeImmutable();

        if (DateHelper::DISPLAY_AUTO === $format) {
            $format = abs($now->getTimestamp() - $date->getTimestamp()) < DateHelper::RELATIVE_AUTO_LIMIT_SECONDS
                ? DateHelper::DISPLAY_RELATIVE
                : DateHelper::DISPLAY_DATE;
        }

        if (DateHelper::DISPLAY_RELATIVE === $format) {
            return $this->formatRelative($date, $now, $locale);
        }

        if (DateHelper::DISPLAY_WEEK === $format || DateHelper::DISPLAY_WEEK_RANGE === $format) {
            return $this->formatWeek($date, DateHelper::DISPLAY_WEEK_RANGE === $format, $locale);
        }

        return $this->formatAbsolute($date, $format, $locale);
    }

    /**
     * The ISO week holding the date: « Week 29 », or « Week 29 · 14–20 Jul » with
     * its Monday-to-Sunday bounds.
     */
    public function formatWeek(
        DateTimeImmutable $date,
        bool $withRange = false,
        ?string $locale = null,
    ): string {
        $parameters = ['%week%' => DateHelper::getWeekNumber($date)];

        if (! $withRange) {
            return ($this->translate)(self::WEEK_KEY, $parameters, $locale);
        }

        $parameters['%range%'] = $this->formatDayRange(
            DateHelper::startOfWeek($date),
            DateHelper::endOfWeek($date),
            $locale
        );

        return ($this->translate)(self::WEEK_RANGE_KEY, $parameters, $locale);
    }

    /**
     * Two days as one range, what they share written once: « 14–20 Jul »,
     * « 28 Jul – 3 Aug », « 29 Dec 2025 – 4 Jan 2026 », in the locale's order.
     *
     * PHP's `intl` has no binding for ICU's interval formatter, so the range is
     * built from the locale's own day-and-month pattern.
     */
    public function formatDayRange(
        DateTimeInterface $start,
        DateTimeInterface $end,
        ?string $locale = null,
    ): string {
        $locale ??= ($this->resolveLocale)();
        $sameYear = $start->format('Y') === $end->format('Y');
        $generator = new IntlDatePatternGenerator($locale);
        $pattern = $generator->getBestPattern($sameYear ? self::RANGE_SKELETON : self::RANGE_SKELETON_YEAR);

        if ($sameYear && $start->format('m') === $end->format('m')) {
            // The end date written once, its day preceded by the first one:
            // `d MMM` becomes `'14–'d MMM`, `MMM d` becomes `MMM '14–'d`.
            $pattern = $this->prefixDayField(
                $pattern,
                $this->formatPattern($start, 'd', $locale).self::RANGE_SEPARATOR_DAYS
            );

            return $this->formatPattern($end, $pattern, $locale);
        }

        return $this->formatPattern($start, $pattern, $locale)
            .self::RANGE_SEPARATOR_DATES
            .$this->formatPattern($end, $pattern, $locale);
    }

    private function formatPattern(
        DateTimeInterface $date,
        string $pattern,
        string $locale,
    ): string {
        $formatter = new IntlDateFormatter($locale, IntlDateFormatter::NONE, IntlDateFormatter::NONE);
        $formatter->setPattern($pattern);

        return $formatter->format($date);
    }

    /**
     * Puts a literal before the day field of an ICU pattern, leaving the quoted
     * literals of the pattern alone — Portuguese writes `d 'de' MMM`.
     */
    private function prefixDayField(
        string $pattern,
        string $literal,
    ): string {
        $quoted = "'".str_replace("'", "''", $literal)."'";
        $parts = preg_split("/('(?:[^']|'')*')/", $pattern, -1, PREG_SPLIT_DELIM_CAPTURE);

        // Even parts are the pattern itself, odd ones the quoted literals the split kept.
        foreach ($parts as $index => $part) {
            if (0 === $index % 2 && str_contains($part, 'd')) {
                $parts[$index] = preg_replace('/d+/', $quoted.'$0', $part, 1);
                break;
            }
        }

        return implode('', $parts);
    }

    public function formatAbsolute(
        DateTimeImmutable $date,
        string $format,
        ?string $locale = null,
    ): string {
        $locale ??= ($this->resolveLocale)();
        [$dateType, $timeType] = self::INTL_STYLES[$format] ?? [IntlDateFormatter::NONE, IntlDateFormatter::NONE];

        $formatter = new IntlDateFormatter($locale, $dateType, $timeType);

        $pattern = self::INTL_SKELETONS[$format] ?? null;

        if (null === $pattern && ! isset(self::INTL_STYLES[$format])) {
            // Not a name we know: the caller handed us an ICU pattern of their own.
            $formatter->setPattern($format);
        } elseif (null !== $pattern) {
            $formatter->setPattern((new IntlDatePatternGenerator($locale))->getBestPattern($pattern));
        }

        return $formatter->format($date);
    }

    public function formatRelative(
        DateTimeImmutable $date,
        ?DateTimeInterface $now = null,
        ?string $locale = null,
    ): string {
        $now ??= new DateTimeImmutable();
        $diff = DateHelper::relativeDiff($now->getTimestamp() - $date->getTimestamp());

        if (DateHelper::RELATIVE_UNIT_NOW === $diff['unit']) {
            return ($this->translate)(self::RELATIVE_KEY_NOW, [], $locale);
        }

        $key = sprintf(
            '%s%s.%s_%s',
            self::RELATIVE_KEY_PREFIX,
            $diff['past'] ? 'past' : 'future',
            $diff['unit'],
            1 === $diff['count'] ? 'one' : 'other'
        );

        return ($this->translate)($key, ['%count%' => $diff['count']], $locale);
    }
}
