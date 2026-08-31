<?php

declare(strict_types=1);

namespace App\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Modules\Academics\Domain\Contracts\ProgramEligibilityEvaluator;
use Modules\Academics\Domain\Contracts\ProgramRulesQueries;
use Modules\Academics\Domain\ValueObjects\ApplicantFacts;
use Modules\Audit\Domain\Contracts\AuditRecorder;
use Modules\Enrollments\Domain\Contracts\EnrollmentPlacementGateway;
use Modules\Enrollments\Domain\ValueObjects\EnrollmentPlacementData;
use Modules\Groups\Domain\Contracts\GroupPlacementGateway;
use Modules\Groups\Domain\Events\StudentAssignedToGroup;
use Modules\Groups\Domain\ValueObjects\GroupPlacementData;
use Modules\Staff\Domain\Contracts\StaffQueries;
use Modules\Staff\Domain\Contracts\TeacherQualificationQueries;
use Modules\Students\Domain\Contracts\StudentPlacementGateway;
use Modules\Students\Domain\Events\StudentAssignedToTeacher;
use Modules\Students\Domain\ValueObjects\StudentPlacementData;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;

/**
 * منسّق رحلة التسكين عبر العقود العامة فقط؛ لا يعرف نماذج أو جداول الموديولات.
 */
final readonly class AssignStudentToGroupAction
{
    public function __construct(
        private StudentPlacementGateway $students,
        private ProgramRulesQueries $academics,
        private ProgramEligibilityEvaluator $eligibility,
        private GroupPlacementGateway $groups,
        private EnrollmentPlacementGateway $enrollments,
        private StaffQueries $staff,
        private TeacherQualificationQueries $qualifications,
        private Transaction $transaction,
        private Dispatcher $events,
        private AuditRecorder $audit,
    ) {}

    public function execute(
        string $actorOrganizationId,
        string $studentProfileId,
        string $programId,
        string $groupId,
        ?string $courseId = null,
        ?string $actorId = null,
        ?string $correlationId = null,
        ?string $reason = null,
    ): EnrollmentPlacementData {
        $reason = $reason === null ? null : trim($reason);

        if ($reason === null || $reason === '') {
            throw BusinessRuleViolation::make(
                'enrollments.placement_reason_required',
                'enrollments::errors.placement_reason_required',
            );
        }

        /** @var array{0: StudentPlacementData, 1: GroupPlacementData, 2: EnrollmentPlacementData, 3: list<array{staff_profile_id: string, teacher_user_id: string, course_id: string|null}>} $result */
        $result = $this->transaction->run(function () use (
            $actorOrganizationId,
            $studentProfileId,
            $programId,
            $groupId,
            $courseId,
            $actorId,
            $correlationId,
            $reason,
        ): array {
            $student = $this->students->findCleared($studentProfileId);

            if ($student === null) {
                throw BusinessRuleViolation::make(
                    'enrollments.student_not_cleared',
                    'enrollments::errors.student_not_cleared',
                );
            }

            if (!hash_equals($student->organizationId, $actorOrganizationId)) {
                throw BusinessRuleViolation::make(
                    'enrollments.organization_mismatch',
                    'enrollments::errors.organization_mismatch',
                );
            }

            $academicContext = $this->academics->placementContext(
                $student->organizationId,
                $programId,
                $courseId,
            );

            if ($academicContext === null) {
                throw BusinessRuleViolation::make(
                    'enrollments.academic_context_invalid',
                    'enrollments::errors.academic_context_invalid',
                );
            }

            $eligibility = $this->eligibility->evaluate($programId, new ApplicantFacts(
                dateOfBirth: $student->dateOfBirth !== null ? CarbonImmutable::parse($student->dateOfBirth) : null,
                gender: $student->gender,
                countryId: $student->countryId,
                regionId: $student->regionId,
            ));

            if (!$eligibility->eligible) {
                throw BusinessRuleViolation::make(
                    'enrollments.eligibility_blocked',
                    'enrollments::errors.eligibility_blocked',
                    ['violations' => implode(',', $eligibility->blocking)],
                );
            }

            $placement = $this->groups->placeStudent(
                organizationId: $student->organizationId,
                groupId: $groupId,
                programId: $programId,
                courseId: $courseId,
                studentProfileId: $studentProfileId,
                requiresSingleMember: $academicContext->sessionMode === 'individual',
            );

            $teacherAssignments = [];
            foreach ($placement->teacherAssignments as $assignment) {
                $teacherUserId = $this->staff->userIdForProfile(
                    $student->organizationId,
                    $assignment['staff_profile_id'],
                );

                if ($teacherUserId === null) {
                    throw BusinessRuleViolation::make(
                        'groups.teacher_profile_invalid',
                        'groups::errors.teacher_profile_invalid',
                    );
                }

                if ($courseId !== null && !$this->qualifications->isQualified(
                    $assignment['staff_profile_id'],
                    $courseId,
                )) {
                    throw BusinessRuleViolation::make(
                        'groups.teacher_not_qualified',
                        'groups::errors.teacher_not_qualified',
                        ['course_id' => $courseId],
                    );
                }

                $teacherAssignments[] = [
                    'staff_profile_id' => $assignment['staff_profile_id'],
                    'teacher_user_id' => $teacherUserId,
                    'course_id' => $assignment['course_id'],
                ];
            }

            $enrollment = $this->enrollments->activate(
                organizationId: $student->organizationId,
                studentProfileId: $studentProfileId,
                programId: $programId,
                reason: $reason,
                actorId: $actorId,
                correlationId: $correlationId,
            );

            $this->students->markAssigned($student->organizationId, $studentProfileId);

            $this->audit->record(
                organizationId: $student->organizationId,
                actorId: $actorId,
                actorType: 'user',
                action: 'enrollment.placed',
                auditableType: 'student_profile',
                auditableId: $studentProfileId,
                oldValues: ['registration_status' => $student->status],
                newValues: [
                    'registration_status' => 'assigned',
                    'program_id' => $programId,
                    'course_id' => $courseId,
                    'group_id' => $groupId,
                    'enrollment_id' => $enrollment->enrollmentId,
                    'membership_id' => $placement->membershipId,
                ],
                reason: $reason,
                correlationId: $correlationId,
            );

            return [$student, $placement, $enrollment, $teacherAssignments];
        });

        [$student, $placement, $enrollment, $teacherAssignments] = $result;

        if ($placement->created) {
            foreach ($teacherAssignments as $assignment) {
                $this->events->dispatch(new StudentAssignedToTeacher(
                    studentProfileId: $student->studentProfileId,
                    studentUserId: $student->studentUserId,
                    teacherProfileId: $assignment['staff_profile_id'],
                    teacherUserId: $assignment['teacher_user_id'],
                    organizationId: $student->organizationId,
                    programId: $programId,
                    courseId: $assignment['course_id'],
                    actorId: $actorId,
                    correlationId: $correlationId,
                    reason: $reason,
                ));
            }

            $this->events->dispatch(new StudentAssignedToGroup(
                membershipId: $placement->membershipId,
                groupId: $placement->groupId,
                organizationId: $placement->organizationId,
                studentProfileId: $student->studentProfileId,
                studentUserId: $student->studentUserId,
                teacherUserIds: array_values(array_unique(array_column($teacherAssignments, 'teacher_user_id'))),
                programId: $programId,
                courseId: $courseId,
                actorId: $actorId,
                correlationId: $correlationId,
                reason: $reason,
            ));
        }

        return $enrollment;
    }
}
