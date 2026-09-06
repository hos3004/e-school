<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\DTO\BulkIndividualSchedulePreview;
use App\Application\DTO\BulkIndividualScheduleResult;
use Carbon\CarbonImmutable;
use Modules\Academics\Domain\Contracts\AcademicCatalogQueries;
use Modules\Academics\Domain\Contracts\ProgramEligibilityEvaluator;
use Modules\Academics\Domain\ValueObjects\AcademicCatalogItemData;
use Modules\Academics\Domain\ValueObjects\ApplicantFacts;
use Modules\Enrollments\Domain\Contracts\EnrollmentPlacementGateway;
use Modules\Scheduling\Application\Actions\CreateScheduleAction;
use Modules\Scheduling\Application\Queries\SchedulingAdministrationQueryService;
use Modules\Scheduling\Application\Services\TeacherAvailabilityPlanner;
use Modules\Students\Domain\Contracts\StudentPlacementGateway;
use Modules\Students\Domain\ValueObjects\StudentPlacementData;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;

/**
 * منسق جذر للتسكين الفردي الجماعي؛ كل طالب يحصل على Schedule وخانة مستقلة.
 */
final readonly class BulkCreateIndividualQuranSchedulesAction
{
    public function __construct(
        private AcademicCatalogQueries $academics,
        private SchedulingAdministrationQueryService $scheduling,
        private TeacherAvailabilityPlanner $availability,
        private CreateScheduleAction $createSchedule,
        private StudentPlacementGateway $students,
        private EnrollmentPlacementGateway $enrollments,
        private ProgramEligibilityEvaluator $eligibility,
        private Transaction $transaction,
    ) {}

    /** @return array<string, string> */
    public function teacherOptions(string $organizationId): array
    {
        $course = $this->course($organizationId);

        return $course === null
            ? []
            : $this->scheduling->teacherOptions($organizationId, null, $course->id);
    }

    /** @return list<string> */
    public function individualQuranStudentIds(string $organizationId): array
    {
        $course = $this->course($organizationId);
        if ($course === null) {
            return [];
        }

        return array_keys($this->scheduling->studentOptions($organizationId, $course->id));
    }

    /** @return array<string, string> student profile ID => schedule ID */
    public function activeScheduleIdsByStudent(string $organizationId): array
    {
        $course = $this->course($organizationId);

        return $course === null
            ? []
            : $this->scheduling->activeIndividualSchedulesByStudent($organizationId, $course->id);
    }

    /** @return list<string> */
    public function eligibleStudentIds(string $organizationId): array
    {
        $schedulable = $this->individualQuranStudentIds($organizationId);
        $scheduled = array_keys($this->activeScheduleIdsByStudent($organizationId));

        return array_values(array_diff($schedulable, $scheduled));
    }

    /**
     * @param list<string> $studentProfileIds
     * @param list<int|string> $weekdays
     */
    public function preview(
        string $organizationId,
        array $studentProfileIds,
        ?string $staffProfileId,
        array $weekdays,
        int $intervalWeeks,
        int $durationMinutes,
        string $timezone,
        ?string $startsOn,
        ?string $endsOn,
        bool $activateEnrollment = false,
    ): BulkIndividualSchedulePreview {
        $selected = $this->normalizeIds($studentProfileIds);
        $this->guardSelectionLimit($selected);
        $course = $this->course($organizationId);
        $eligible = $course === null
            ? []
            : ($activateEnrollment
                ? $this->placementReadyStudentIds($organizationId, $selected, $course)
                : array_values(array_intersect($selected, $this->eligibleStudentIds($organizationId))));

        if ($staffProfileId === null || $staffProfileId === '' || $weekdays === []) {
            return new BulkIndividualSchedulePreview(count($selected), $eligible, 0, []);
        }

        $slots = $this->nonOverlappingTimes($this->availableStartTimes(
            organizationId: $organizationId,
            staffProfileId: $staffProfileId,
            weekdays: $weekdays,
            intervalWeeks: $intervalWeeks,
            durationMinutes: $durationMinutes,
            timezone: $timezone,
            startsOn: $startsOn,
            endsOn: $endsOn,
        ), $durationMinutes);

        return new BulkIndividualSchedulePreview(
            selectedCount: count($selected),
            eligibleStudentIds: $eligible,
            availableSlotCount: count($slots),
            assignedStartTimes: array_slice($slots, 0, count($eligible)),
        );
    }

    /**
     * @param list<int|string> $weekdays
     * @return list<string>
     */
    public function availableStartTimes(
        string $organizationId,
        ?string $staffProfileId,
        array $weekdays,
        int $intervalWeeks,
        int $durationMinutes,
        string $timezone,
        ?string $startsOn,
        ?string $endsOn,
    ): array {
        if ($staffProfileId === null || $staffProfileId === '' || $weekdays === []) {
            return [];
        }

        $overview = $this->availability->overview(
            organizationId: $organizationId,
            staffProfileId: $staffProfileId,
            weekdays: $weekdays,
            intervalWeeks: max(1, $intervalWeeks),
            durationMinutes: $durationMinutes,
            timezone: $timezone,
            startsOn: $startsOn,
            endsOn: $endsOn,
            requireDeclaredAvailability: false,
        );

        return $overview['available_start_times'];
    }

    /** @param list<int|string> $weekdays */
    public function executeSingle(
        string $organizationId,
        string $studentProfileId,
        string $staffProfileId,
        array $weekdays,
        int $intervalWeeks,
        int $durationMinutes,
        string $startTime,
        string $timezone,
        string $startsOn,
        ?string $endsOn,
        string $actorId,
        string $reason,
    ): string {
        $course = $this->course($organizationId);
        if ($course === null) {
            throw BusinessRuleViolation::make(
                'scheduling.individual_quran_course_missing',
                'scheduling::errors.individual_quran_course_missing',
            );
        }

        if (!in_array($studentProfileId, $this->eligibleStudentIds($organizationId), true)) {
            throw BusinessRuleViolation::make(
                'scheduling.individual_student_not_eligible',
                'scheduling::errors.individual_student_not_eligible',
            );
        }

        if (!array_key_exists($staffProfileId, $this->teacherOptions($organizationId))) {
            throw BusinessRuleViolation::make(
                'scheduling.teacher_not_eligible',
                'scheduling::errors.teacher_not_eligible',
            );
        }

        $availableTimes = $this->availableStartTimes(
            organizationId: $organizationId,
            staffProfileId: $staffProfileId,
            weekdays: $weekdays,
            intervalWeeks: $intervalWeeks,
            durationMinutes: $durationMinutes,
            timezone: $timezone,
            startsOn: $startsOn,
            endsOn: $endsOn,
        );
        if (!in_array($startTime, $availableTimes, true)) {
            throw BusinessRuleViolation::make(
                'scheduling.individual_slot_unavailable',
                'scheduling::errors.individual_slot_unavailable',
            );
        }

        return $this->transaction->run(function () use (
            $organizationId,
            $studentProfileId,
            $course,
            $staffProfileId,
            $weekdays,
            $intervalWeeks,
            $startTime,
            $durationMinutes,
            $timezone,
            $startsOn,
            $endsOn,
            $actorId,
            $reason,
        ): string {
            $schedule = $this->createSchedule->execute($organizationId, [
                'target_type' => 'student',
                'student_profile_id' => $studentProfileId,
                'course_id' => $course->id,
                'staff_profile_id' => $staffProfileId,
                'weekdays' => $weekdays,
                'interval_weeks' => max(1, $intervalWeeks),
                'start_time' => $startTime,
                'duration_minutes' => $durationMinutes,
                'timezone' => $timezone,
                'starts_on' => $startsOn,
                'ends_on' => $endsOn,
            ], $actorId, $reason);

            if ($this->students->findCleared($studentProfileId) !== null) {
                $this->students->markAssigned($organizationId, $studentProfileId);
            }

            return (string) $schedule->getKey();
        });
    }

    /**
     * @param list<string> $studentProfileIds
     * @param list<int|string> $weekdays
     */
    public function execute(
        string $organizationId,
        array $studentProfileIds,
        string $staffProfileId,
        array $weekdays,
        int $intervalWeeks,
        int $durationMinutes,
        string $timezone,
        string $startsOn,
        ?string $endsOn,
        string $actorId,
        string $reason,
        bool $activateEnrollment = false,
        ?string $correlationId = null,
    ): BulkIndividualScheduleResult {
        $course = $this->course($organizationId);
        if ($course === null) {
            throw BusinessRuleViolation::make(
                'scheduling.individual_quran_course_missing',
                'scheduling::errors.individual_quran_course_missing',
            );
        }

        $preview = $this->preview(
            $organizationId,
            $studentProfileIds,
            $staffProfileId,
            $weekdays,
            $intervalWeeks,
            $durationMinutes,
            $timezone,
            $startsOn,
            $endsOn,
            $activateEnrollment,
        );
        if ($preview->eligibleCount() === 0) {
            throw BusinessRuleViolation::make(
                'scheduling.bulk_no_eligible_students',
                'scheduling::errors.bulk_no_eligible_students',
            );
        }
        if (!$preview->hasEnoughSlots()) {
            throw BusinessRuleViolation::make(
                'scheduling.bulk_insufficient_slots',
                'scheduling::errors.bulk_insufficient_slots',
                ['students' => $preview->eligibleCount(), 'slots' => $preview->availableSlotCount],
            );
        }

        $created = [];
        $failed = [];
        foreach ($preview->eligibleStudentIds as $index => $studentProfileId) {
            try {
                $schedule = $this->transaction->run(function () use (
                    $activateEnrollment,
                    $organizationId,
                    $studentProfileId,
                    $course,
                    $reason,
                    $actorId,
                    $correlationId,
                    $staffProfileId,
                    $weekdays,
                    $intervalWeeks,
                    $preview,
                    $index,
                    $durationMinutes,
                    $timezone,
                    $startsOn,
                    $endsOn,
                ) {
                    if ($activateEnrollment) {
                        $this->enrollments->activate(
                            organizationId: $organizationId,
                            studentProfileId: $studentProfileId,
                            programId: (string) $course->programId,
                            reason: $reason,
                            actorId: $actorId,
                            correlationId: $correlationId,
                        );
                    }

                    $schedule = $this->createSchedule->execute($organizationId, [
                        'target_type' => 'student',
                        'student_profile_id' => $studentProfileId,
                        'course_id' => $course->id,
                        'staff_profile_id' => $staffProfileId,
                        'weekdays' => $weekdays,
                        'interval_weeks' => max(1, $intervalWeeks),
                        'start_time' => $preview->assignedStartTimes[$index],
                        'duration_minutes' => $durationMinutes,
                        'timezone' => $timezone,
                        'starts_on' => $startsOn,
                        'ends_on' => $endsOn,
                    ], $actorId, $reason);

                    if ($this->students->findCleared($studentProfileId) !== null) {
                        $this->students->markAssigned($organizationId, $studentProfileId);
                    }

                    return $schedule;
                });
                $created[$studentProfileId] = (string) $schedule->getKey();
            } catch (BusinessRuleViolation $violation) {
                $failed[$studentProfileId] = $violation->getMessage();
            }
        }

        return new BulkIndividualScheduleResult($created, $failed, $preview->blockedCount());
    }

    private function course(string $organizationId): ?AcademicCatalogItemData
    {
        $code = (string) config('scheduling.individual_quran.course_code');

        return collect($this->academics->programs($organizationId))
            ->flatMap(fn (AcademicCatalogItemData $program): array => $this->academics->courses(
                $organizationId,
                $program->id,
            ))
            ->first(static fn (AcademicCatalogItemData $course): bool => $course->code === $code
                && in_array($course->sessionMode, ['individual', 'both'], true));
    }

    /**
     * @param list<string> $studentProfileIds
     * @return list<string>
     */
    private function placementReadyStudentIds(
        string $organizationId,
        array $studentProfileIds,
        AcademicCatalogItemData $course,
    ): array {
        if ($course->programId === null) {
            return [];
        }

        $scheduled = $this->scheduling->activeIndividualStudentIds($organizationId, $course->id);
        $eligible = [];

        foreach ($studentProfileIds as $studentProfileId) {
            $student = $this->students->findCleared($studentProfileId);
            if ($student === null
                || !hash_equals($organizationId, $student->organizationId)
                || in_array($studentProfileId, $scheduled, true)
                || !$this->isEligibleForProgram($student, $course->programId)) {
                continue;
            }

            $eligible[] = $studentProfileId;
        }

        return $eligible;
    }

    private function isEligibleForProgram(StudentPlacementData $student, string $programId): bool
    {
        return $this->eligibility->evaluate($programId, new ApplicantFacts(
            dateOfBirth: $student->dateOfBirth === null ? null : CarbonImmutable::parse($student->dateOfBirth),
            gender: $student->gender,
            countryId: $student->countryId,
            regionId: $student->regionId,
        ))->eligible;
    }

    /**
     * @param list<string> $ids
     * @return list<string>
     */
    private function normalizeIds(array $ids): array
    {
        return array_values(array_unique(array_filter(
            $ids,
            static fn (mixed $id): bool => is_string($id) && $id !== '',
        )));
    }

    /** @param list<string> $ids */
    private function guardSelectionLimit(array $ids): void
    {
        $maximum = max(1, (int) config('scheduling.individual_quran.bulk_max_students'));
        if (count($ids) > $maximum) {
            throw BusinessRuleViolation::make(
                'scheduling.bulk_limit_exceeded',
                'scheduling::errors.bulk_limit_exceeded',
                ['maximum' => $maximum],
            );
        }
    }

    /**
     * @param list<string> $times
     * @return list<string>
     */
    private function nonOverlappingTimes(array $times, int $durationMinutes): array
    {
        $selected = [];
        $previousEnd = null;
        foreach ($times as $time) {
            [$hour, $minute] = array_map('intval', explode(':', $time));
            $start = ($hour * 60) + $minute;
            if ($previousEnd !== null && $start < $previousEnd) {
                continue;
            }

            $selected[] = $time;
            $previousEnd = $start + $durationMinutes;
        }

        return $selected;
    }
}
