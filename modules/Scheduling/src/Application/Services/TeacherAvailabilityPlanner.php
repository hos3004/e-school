<?php

declare(strict_types=1);

namespace Modules\Scheduling\Application\Services;

use Carbon\CarbonImmutable;
use DateTimeZone;
use Modules\Scheduling\Domain\ValueObjects\WeeklyRecurrence;
use Modules\Sessions\Domain\Contracts\SessionSchedulingQueries;
use Modules\Sessions\Domain\ValueObjects\SessionSchedulingData;
use Modules\Staff\Domain\Contracts\StaffAdministrationQueries;
use Modules\Staff\Domain\ValueObjects\TeacherAvailabilityData;
use Shared\Support\BusinessRuleViolation;
use Shared\ValueObjects\TimeRange;

/**
 * يحوّل الإتاحة الأسبوعية للمعلم وحجوزاته الفعلية إلى خانات قابلة للاختيار.
 * كل المقارنات الداخلية UTC، بينما قيم الاختيار تُعرض بتوقيت الجدول.
 */
final readonly class TeacherAvailabilityPlanner
{
    public function __construct(
        private StaffAdministrationQueries $staff,
        private SessionSchedulingQueries $sessions,
    ) {}

    /**
     * @param list<int|string> $weekdays
     * @return array{
     *   available_start_times: list<string>,
     *   booked_sessions: list<array{start: CarbonImmutable, end: CarbonImmutable}>,
     *   planned_occurrences: list<array{start: CarbonImmutable, end: CarbonImmutable, available: bool}>,
     *   total_occurrences: int,
     *   has_declared_availability: bool
     * }
     */
    public function overview(
        string $organizationId,
        ?string $staffProfileId,
        array $weekdays,
        int $intervalWeeks,
        int $durationMinutes,
        string $timezone,
        ?string $startsOn,
        ?string $endsOn,
        ?string $selectedStartTime = null,
        bool $requireDeclaredAvailability = true,
        ?string $ignoreScheduleId = null,
    ): array {
        $empty = [
            'available_start_times' => [],
            'booked_sessions' => [],
            'planned_occurrences' => [],
            'total_occurrences' => 0,
            'has_declared_availability' => false,
        ];

        if ($staffProfileId === null || $staffProfileId === '' || $weekdays === []
            || $durationMinutes < 1 || $startsOn === null || $startsOn === '') {
            return $empty;
        }

        try {
            new DateTimeZone($timezone);
            $anchor = CarbonImmutable::parse($startsOn, $timezone)->startOfDay();
            $requestedEnd = $endsOn === null || $endsOn === ''
                ? null
                : CarbonImmutable::parse($endsOn, $timezone)->endOfDay();
        } catch (\Throwable) {
            return $empty;
        }

        $now = CarbonImmutable::now('UTC');
        $through = $now
            ->addDays((int) config('scheduling.availability.compatibility_horizon_days'))
            ->setTimezone($timezone)
            ->endOfDay();
        if ($requestedEnd !== null) {
            $through = $through->min($requestedEnd);
        }
        if ($through->lessThan($anchor)) {
            return $empty;
        }

        $from = $anchor->utc()->max($now);
        $rule = WeeklyRecurrence::fromWeekdays($weekdays, $intervalWeeks);
        $availability = array_values(array_filter(
            $this->staff->availabilityForTeacher($organizationId, $staffProfileId),
            static fn (TeacherAvailabilityData $slot): bool => $slot->approvalStatus === 'approved',
        ));
        $bookings = array_values(array_filter(
            $this->sessions->bookingsForTeacher(
                $organizationId,
                $staffProfileId,
                $from,
                $through->utc(),
            ),
            static fn (SessionSchedulingData $session): bool => $ignoreScheduleId === null
                || $session->scheduleId !== $ignoreScheduleId,
        ));

        $availableTimes = [];
        foreach ($this->candidateTimes($durationMinutes) as $candidate) {
            $ranges = $this->futureOccurrences(
                $rule,
                $anchor,
                $from,
                $through,
                $candidate,
                $durationMinutes,
                $timezone,
            );

            if ($ranges !== [] && $this->allOccurrencesAvailable(
                $ranges,
                $availability,
                $bookings,
                $requireDeclaredAvailability,
            )) {
                $availableTimes[] = $candidate;
            }
        }

        $planned = [];
        if ($selectedStartTime !== null && $selectedStartTime !== '') {
            foreach ($this->futureOccurrences(
                $rule,
                $anchor,
                $from,
                $through,
                $selectedStartTime,
                $durationMinutes,
                $timezone,
            ) as $range) {
                $planned[] = [
                    'start' => $range->start,
                    'end' => $range->end,
                    'available' => $this->allOccurrencesAvailable(
                        [$range],
                        $availability,
                        $bookings,
                        $requireDeclaredAvailability,
                    ),
                ];
            }
        }

        return [
            'available_start_times' => $availableTimes,
            'booked_sessions' => array_map(static fn (SessionSchedulingData $session): array => [
                'start' => $session->scheduledStart,
                'end' => $session->scheduledEnd,
            ], $bookings),
            'planned_occurrences' => $planned,
            'total_occurrences' => count($planned),
            'has_declared_availability' => $availability !== [],
        ];
    }

    /** @return list<string> */
    private function candidateTimes(int $durationMinutes): array
    {
        $start = self::minutes((string) config('scheduling.booking_slots.day_start'));
        $end = self::minutes((string) config('scheduling.booking_slots.day_end'));
        $step = max(1, (int) config('scheduling.booking_slots.interval_minutes'));
        $times = [];

        for ($minute = $start; $minute + $durationMinutes <= $end; $minute += $step) {
            $times[] = sprintf('%02d:%02d', intdiv($minute, 60), $minute % 60);
        }

        return $times;
    }

    /** @return list<TimeRange> */
    private function futureOccurrences(
        WeeklyRecurrence $rule,
        CarbonImmutable $anchor,
        CarbonImmutable $from,
        CarbonImmutable $through,
        string $startTime,
        int $durationMinutes,
        string $timezone,
    ): array {
        return array_values(array_filter(
            $rule->occurrences(
                anchorDate: $anchor,
                fromDate: $from->setTimezone($timezone),
                throughDate: $through,
                startTime: $startTime,
                durationMinutes: $durationMinutes,
                timezone: $timezone,
            ),
            static fn (TimeRange $range): bool => $range->start->greaterThan(CarbonImmutable::now('UTC')),
        ));
    }

    /**
     * @param list<TimeRange> $ranges
     * @param list<TeacherAvailabilityData> $availability
     * @param list<SessionSchedulingData> $bookings
     */
    private function allOccurrencesAvailable(
        array $ranges,
        array $availability,
        array $bookings,
        bool $requireDeclaredAvailability,
    ): bool {
        foreach ($ranges as $range) {
            $withinAvailability = $this->withinAvailability($range, $availability);
            if (($requireDeclaredAvailability || $availability !== []) && !$withinAvailability) {
                return false;
            }

            foreach ($bookings as $booking) {
                if ($range->start->lessThan($booking->scheduledEnd)
                    && $booking->scheduledStart->lessThan($range->end)) {
                    return false;
                }
            }
        }

        return true;
    }

    /** @param list<TeacherAvailabilityData> $availability */
    private function withinAvailability(TimeRange $range, array $availability): bool
    {
        foreach ($availability as $slot) {
            $localStart = $range->start->setTimezone($slot->timezone);
            $localEnd = $range->end->setTimezone($slot->timezone);
            $date = $localStart->toDateString();

            if ($date !== $localEnd->toDateString()
                || (int) $localStart->dayOfWeek !== $slot->weekday
                || $date < $slot->effectiveFrom
                || ($slot->effectiveTo !== null && $date > $slot->effectiveTo)) {
                continue;
            }

            if ($localStart->format('H:i:s') >= $slot->startTime
                && $localEnd->format('H:i:s') <= $slot->endTime) {
                return true;
            }
        }

        return false;
    }

    private static function minutes(string $time): int
    {
        if (!preg_match('/^(\d{2}):(\d{2})$/', $time, $matches)) {
            throw BusinessRuleViolation::make(
                'scheduling.booking_slot_config_invalid',
                'scheduling::errors.booking_slot_config_invalid',
            );
        }

        return ((int) $matches[1] * 60) + (int) $matches[2];
    }
}
