<?php

declare(strict_types=1);

namespace App\Application\Actions;

use Modules\Audit\Domain\Contracts\AuditRecorder;
use Modules\Enrollments\Domain\ValueObjects\EnrollmentPlacementData;
use Modules\Groups\Domain\Contracts\GroupAssignmentOperations;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;

/**
 * نقل طالب من مجموعة إلى أخرى — بما يشمل الانتقال من حلقة معلم إلى حلقة معلم آخر.
 *
 * تركيب ذري لعمليتين قائمتين: سحب الانتساب القديم ثم التسكين الجديد عبر مسار
 * التسكين الرسمي، فتبقى الأهلية والسعة والصلاحيات والقيد في مكان واحد.
 *
 * السحب والتسكين يطلقان أحداثهما، والجدولة تسمعها بالفعل: تُسحب حصص المعلم
 * السابق المستقبلية ويُضاف الطالب لحصص المجموعة الجديدة. الحصص الماضية وحضورها
 * لا تُمس. وإذا فشل أي جزء تُلغى المعاملة كاملة فلا يبقى الطالب بلا مجموعة.
 *
 * `$fromGroupId` يأتي من خدمة قراءة الانتسابات في طبقة الاستدعاء، لا من المتصفح،
 * ويُستخدم للتدقيق ولمنع النقل إلى نفس المجموعة.
 */
final readonly class TransferStudentAction
{
    public function __construct(
        private GroupAssignmentOperations $groups,
        private AssignStudentToGroupAction $assign,
        private Transaction $transaction,
        private AuditRecorder $audit,
    ) {}

    public function execute(
        string $actorOrganizationId,
        string $studentProfileId,
        string $fromMembershipId,
        string $fromGroupId,
        string $programId,
        string $groupId,
        ?string $courseId,
        string $reason,
        string $actorId,
        ?string $correlationId = null,
    ): EnrollmentPlacementData {
        $reason = trim($reason);

        if ($reason === '') {
            throw BusinessRuleViolation::make(
                'enrollments.placement_reason_required',
                'enrollments::errors.placement_reason_required',
            );
        }

        return $this->transaction->run(function () use (
            $actorOrganizationId,
            $studentProfileId,
            $fromMembershipId,
            $fromGroupId,
            $programId,
            $groupId,
            $courseId,
            $reason,
            $actorId,
            $correlationId,
        ): EnrollmentPlacementData {
            $this->groups->withdrawStudent($actorOrganizationId, $fromMembershipId, $actorId, $reason);

            $enrollment = $this->assign->execute(
                actorOrganizationId: $actorOrganizationId,
                studentProfileId: $studentProfileId,
                programId: $programId,
                groupId: $groupId,
                courseId: $courseId,
                actorId: $actorId,
                correlationId: $correlationId,
                reason: $reason,
            );

            $this->audit->record(
                organizationId: $actorOrganizationId,
                actorId: $actorId,
                actorType: 'user',
                action: 'students.transferred',
                auditableType: 'student_profile',
                auditableId: $studentProfileId,
                oldValues: ['membership_id' => $fromMembershipId, 'group_id' => $fromGroupId],
                newValues: [
                    'group_id' => $groupId,
                    'program_id' => $programId,
                    'course_id' => $courseId,
                    'enrollment_id' => $enrollment->enrollmentId,
                ],
                reason: $reason,
                correlationId: $correlationId,
            );

            return $enrollment;
        });
    }
}
