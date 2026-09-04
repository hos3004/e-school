<?php

declare(strict_types=1);

namespace Modules\Scheduling\Application\Services;

use Carbon\CarbonImmutable;
use Modules\Academics\Domain\Contracts\AcademicCatalogQueries;
use Modules\Enrollments\Domain\Contracts\EnrollmentAdministrationQueries;
use Modules\Groups\Domain\Contracts\GroupAdministrationQueries;
use Modules\Scheduling\Application\Data\MaterializationResult;
use Modules\Scheduling\Domain\Models\Schedule;
use Modules\Scheduling\Domain\ValueObjects\WeeklyRecurrence;
use Modules\Sessions\Domain\Contracts\SessionSchedulingGateway;
use Modules\Sessions\Domain\ValueObjects\ScheduledParticipantData;
use Modules\Staff\Domain\Contracts\StaffQueries;
use Shared\Support\BusinessRuleViolation;

final readonly class ScheduleMaterializer
{
    public function __construct(
        private AcademicCatalogQueries $academics,
        private GroupAdministrationQueries $groups,
        private EnrollmentAdministrationQueries $enrollments,
        private StaffQueries $staff,
        private SessionSchedulingGateway $sessions,
    ) {}

    public function materialize(
        Schedule $schedule,
        ?string $actorId = null,
        ?CarbonImmutable $from = null,
    ): MaterializationResult {
        if (!$schedule->is_active) {
            throw BusinessRuleViolation::make('scheduling.schedule_inactive', 'scheduling::errors.schedule_inactive');
        }

        $now = CarbonImmutable::now('UTC');
        $horizon = $now->addDays((int) config('scheduling.recurrence.materialize_ahead_days'));
        if ($schedule->ends_on !== null) {
            $scheduleEnd = CarbonImmutable::parse($schedule->ends_on->toDateString(), $schedule->timezone)->endOfDay()->utc();
            $horizon = $horizon->min($scheduleEnd);
        }

        $startsOn = CarbonImmutable::parse($schedule->starts_on->toDateString(), $schedule->timezone);
        $fromMoment = ($from ?? $now)->max($startsOn->utc());
        $rule = WeeklyRecurrence::fromRRule((string) $schedule->rrule);
        $ranges = $rule->occurrences(
            anchorDate: $startsOn,
            fromDate: $fromMoment->setTimezone($schedule->timezone),
            throughDate: $horizon->setTimezone($schedule->timezone),
            startTime: (string) $schedule->start_time,
            durationMinutes: (int) $schedule->duration_minutes,
            timezone: (string) $schedule->timezone,
        );

        $course = $this->academics->coursesByIds(
            (string) $schedule->organization_id,
            [(string) $schedule->course_id],
        )[(string) $schedule->course_id] ?? null;
        if ($course === null || $course->programId === null) {
            throw BusinessRuleViolation::make('scheduling.course_not_found', 'scheduling::errors.course_not_found');
        }

        $participants = $this->participants($schedule, $course->programId);
        $created = 0;
        $warnings = 0;

        foreach ($ranges as $range) {
            if ($range->start->lessThanOrEqualTo($now)) {
                continue;
            }

            if ($this->staff->isOnApprovedLeave((string) $schedule->staff_profile_id, $range->start, $range->end)) {
                throw BusinessRuleViolation::make(
                    'scheduling.teacher_on_leave',
                    'scheduling::errors.teacher_on_leave',
                    ['date' => $range->start->setTimezone($schedule->timezone)->toDateString()],
                );
            }

            $hasDeclaredAvailability = $this->staff->hasDeclaredAvailability(
                (string) $schedule->staff_profile_id,
                $range->start,
            );
            $isWithinAvailability = $this->staff->isAvailableDuring(
                (string) $schedule->staff_profile_id,
                $range->start,
                $range->end,
            );
            $individualRequiresDeclared = (string) $schedule->session_type === 'individual'
                && (bool) config('scheduling.availability.individual_requires_declared');

            if ($individualRequiresDeclared && (!$hasDeclaredAvailability || !$isWithinAvailability)) {
                throw BusinessRuleViolation::make(
                    'scheduling.outside_teacher_availability',
                    'scheduling::errors.outside_teacher_availability',
                );
            }

            if (!$individualRequiresDeclared && $hasDeclaredAvailability && !$isWithinAvailability) {
                if ((string) config('scheduling.availability.outside_declared') === 'block') {
                    throw BusinessRuleViolation::make(
                        'scheduling.outside_teacher_availability',
                        'scheduling::errors.outside_teacher_availability',
                    );
                }
                $warnings++;
            }

            $this->sessions->createScheduledSession(
                organizationId: (string) $schedule->organization_id,
                scheduleId: (string) $schedule->getKey(),
                groupId: $schedule->group_id === null ? null : (string) $schedule->group_id,
                courseId: (string) $schedule->course_id,
                staffProfileId: (string) $schedule->staff_profile_id,
                sessionType: (string) $schedule->session_type,
                startsAt: $range->start,
                endsAt: $range->end,
                title: $course->name,
                participants: $participants,
                actorId: $actorId,
            );
            $created++;
        }

        $materializedUntil = $horizon->setTimezone($schedule->timezone)->toDateString();
        $schedule->forceFill(['materialized_until' => $materializedUntil])->save();

        return new MaterializationResult($created, $warnings, $materializedUntil);
    }

    /** @return list<ScheduledParticipantData> */
    private function participants(Schedule $schedule, string $programId): array
    {
        if ($schedule->student_profile_id !== null) {
            $studentId = (string) $schedule->student_profile_id;
            $enrollments = $this->enrollments->schedulableEnrollmentIdsByStudent(
                (string) $schedule->organization_id,
                $programId,
                [$studentId],
            );
            if (!isset($enrollments[$studentId])) {
                throw BusinessRuleViolation::make('scheduling.student_not_schedulable', 'scheduling::errors.student_not_schedulable');
            }

            return [new ScheduledParticipantData($studentId, $enrollments[$studentId])];
        }

        $members = $this->groups->membershipsForGroup(
            (string) $schedule->organization_id,
            (string) $schedule->group_id,
        );
        $studentIds = collect($members)
            ->filter(static fn ($member): bool => $member->status === 'active' && $member->leftAt === null)
            ->map(static fn ($member): string => $member->studentProfileId)
            ->values()
            ->all();
        $enrollments = $this->enrollments->schedulableEnrollmentIdsByStudent(
            (string) $schedule->organization_id,
            $programId,
            $studentIds,
        );

        return collect($studentIds)
            ->filter(static fn (string $studentId): bool => isset($enrollments[$studentId]))
            ->map(static fn (string $studentId): ScheduledParticipantData => new ScheduledParticipantData(
                $studentId,
                $enrollments[$studentId],
            ))
            ->values()
            ->all();
    }
}
