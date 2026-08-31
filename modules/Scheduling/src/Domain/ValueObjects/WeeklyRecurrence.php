<?php

declare(strict_types=1);

namespace Modules\Scheduling\Domain\ValueObjects;

use Carbon\CarbonImmutable;
use DateTimeZone;
use Shared\Support\BusinessRuleViolation;
use Shared\ValueObjects\TimeRange;

/** الجزء الأسبوعي المدعوم من RFC 5545 والمولّد حصريًا من واجهة الإدارة. */
final readonly class WeeklyRecurrence
{
    /** @var array<int, string> */
    private const DAY_CODES = [
        0 => 'SU',
        1 => 'MO',
        2 => 'TU',
        3 => 'WE',
        4 => 'TH',
        5 => 'FR',
        6 => 'SA',
    ];

    /** @param list<int> $weekdays */
    public function __construct(
        public array $weekdays,
        public int $intervalWeeks = 1,
    ) {
        if ($weekdays === [] || array_diff($weekdays, array_keys(self::DAY_CODES)) !== []) {
            throw BusinessRuleViolation::make('scheduling.weekdays_invalid', 'scheduling::errors.weekdays_invalid');
        }

        if ($intervalWeeks < 1 || $intervalWeeks > 12) {
            throw BusinessRuleViolation::make('scheduling.interval_invalid', 'scheduling::errors.interval_invalid');
        }
    }

    /** @param list<int|string> $weekdays */
    public static function fromWeekdays(array $weekdays, int $intervalWeeks = 1): self
    {
        $normalized = array_values(array_unique(array_map('intval', $weekdays)));
        sort($normalized);

        return new self($normalized, $intervalWeeks);
    }

    public static function fromRRule(string $rrule): self
    {
        $parts = [];
        foreach (explode(';', strtoupper(trim($rrule))) as $part) {
            [$key, $value] = array_pad(explode('=', $part, 2), 2, null);
            if (is_string($key) && is_string($value)) {
                $parts[$key] = $value;
            }
        }

        if (($parts['FREQ'] ?? null) !== 'WEEKLY' || !isset($parts['BYDAY'])) {
            throw BusinessRuleViolation::make('scheduling.rrule_invalid', 'scheduling::errors.rrule_invalid');
        }

        $codes = array_flip(self::DAY_CODES);
        $days = [];
        foreach (explode(',', $parts['BYDAY']) as $code) {
            if (!array_key_exists($code, $codes)) {
                throw BusinessRuleViolation::make('scheduling.rrule_invalid', 'scheduling::errors.rrule_invalid');
            }
            $days[] = (int) $codes[$code];
        }

        return self::fromWeekdays($days, (int) ($parts['INTERVAL'] ?? 1));
    }

    public function toRRule(): string
    {
        $codes = array_map(static fn (int $day): string => self::DAY_CODES[$day], $this->weekdays);

        return 'FREQ=WEEKLY;INTERVAL='.$this->intervalWeeks.';BYDAY='.implode(',', $codes);
    }

    /**
     * @return list<TimeRange>
     */
    public function occurrences(
        CarbonImmutable $anchorDate,
        CarbonImmutable $fromDate,
        CarbonImmutable $throughDate,
        string $startTime,
        int $durationMinutes,
        string $timezone,
    ): array {
        try {
            new DateTimeZone($timezone);
        } catch (\Throwable) {
            throw BusinessRuleViolation::make('scheduling.timezone_invalid', 'scheduling::errors.timezone_invalid');
        }

        $anchor = $anchorDate->startOfDay();
        $cursor = $fromDate->startOfDay();
        $through = $throughDate->endOfDay();
        $ranges = [];

        while ($cursor->lessThanOrEqualTo($through)) {
            $daysSinceAnchor = (int) $anchor->diffInDays($cursor, false);
            if ($daysSinceAnchor >= 0) {
                $weekIndex = intdiv($daysSinceAnchor + (int) $anchor->dayOfWeek, 7);
                if ($weekIndex % $this->intervalWeeks === 0
                    && in_array((int) $cursor->dayOfWeek, $this->weekdays, true)) {
                    $localStart = CarbonImmutable::parse(
                        $cursor->format('Y-m-d').' '.$startTime,
                        $timezone,
                    );
                    $ranges[] = TimeRange::fromDuration($localStart->utc(), $durationMinutes);
                }
            }
            $cursor = $cursor->addDay();
        }

        return $ranges;
    }
}
