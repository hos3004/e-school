<?php

declare(strict_types=1);

namespace Modules\Staff\Application\Actions;

use Carbon\CarbonImmutable;
use Modules\Audit\Domain\Contracts\AuditRecorder;
use Modules\Groups\Domain\Contracts\GroupAdministrationQueries;
use Modules\Staff\Domain\Models\StaffProfile;
use Modules\Staff\Domain\Models\TeacherCourse;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;

/**
 * إلغاء اعتماد معلم على كورس — تعليق موثق لا حذف.
 *
 * لا يُلغى اعتماد يكسر إسنادًا نشطًا بصمت: أي إسناد مفتوح على نفس
 * الكورس يمنع الإلغاء حتى يُنهى عبر مسار المجموعات الرسمي.
 */
final readonly class RevokeTeacherQualificationAction
{
    public function __construct(
        private GroupAdministrationQueries $groups,
        private AuditRecorder $audit,
        private Transaction $transaction,
    ) {}

    public function execute(
        StaffProfile $profile,
        string $courseId,
        string $actorId,
        string $reason,
    ): TeacherCourse {
        if (trim($reason) === '') {
            throw BusinessRuleViolation::make(
                'staff.revocation_reason_required',
                'staff::errors.revocation_reason_required',
            );
        }

        $this->assertNoActiveAssignment($profile, $courseId);

        /** @var TeacherCourse $qualification */
        $qualification = TeacherCourse::query()
            ->where('staff_profile_id', (string) $profile->getKey())
            ->where('course_id', $courseId)
            ->firstOrFail();

        if ($qualification->isRevoked()) {
            throw BusinessRuleViolation::make(
                'staff.qualification_already_revoked',
                'staff::errors.qualification_already_revoked',
                ['course_id' => $courseId],
            );
        }

        $this->transaction->run(function () use ($qualification, $profile, $actorId, $reason): void {
            $qualification->revoked_at = now()->utc();
            $qualification->revoked_by = $actorId;
            $qualification->revocation_reason = trim($reason);
            $qualification->save();

            $this->audit->record(
                organizationId: (string) $profile->organization_id,
                actorId: $actorId,
                actorType: 'user',
                action: 'staff.qualification_revoked',
                auditableType: 'teacher_course',
                auditableId: (string) $qualification->getKey(),
                oldValues: ['revoked_at' => null],
                newValues: [
                    'revoked_at' => $qualification->revoked_at->toIso8601String(),
                    'course_id' => $qualification->course_id,
                ],
                reason: trim($reason),
            );
        });

        return $qualification;
    }

    private function assertNoActiveAssignment(StaffProfile $profile, string $courseId): void
    {
        $now = CarbonImmutable::now('UTC');
        $active = $this->groups->assignmentsForTeacher(
            (string) $profile->organization_id,
            (string) $profile->getKey(),
        );

        foreach ($active as $assignment) {
            $isOpen = $assignment->assignedTo === null
                || CarbonImmutable::parse($assignment->assignedTo)->gt($now);

            if ($isOpen && $assignment->courseId === $courseId) {
                throw BusinessRuleViolation::make(
                    'staff.qualification_active_assignment',
                    'staff::errors.qualification_active_assignment',
                    ['group_id' => $assignment->groupId, 'course_id' => $courseId],
                );
            }
        }
    }
}
