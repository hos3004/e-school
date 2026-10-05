<?php

declare(strict_types=1);

namespace App\Application\Actions;

use Modules\Enrollments\Domain\ValueObjects\EnrollmentPlacementData;
use Modules\Students\Application\Actions\EnrollExistingStudentInWaitlistAction;
use Shared\Support\BusinessRuleViolation;

/**
 * واجهة واحدة لإداري يريد إضافة طالب له حساب وملف بالفعل مباشرة لمجموعة:
 * تضعه في قائمة انتظار الكورس (أو تعيد استخدام طلب سابق مكتمل له) ثم تسكّنه
 * في نفس الخطوة — دون شاشة وسيطة أو سبب يكتبه الإداري في كل مرة.
 */
final readonly class EnrollExistingStudentIntoGroupAction
{
    public function __construct(
        private EnrollExistingStudentInWaitlistAction $waitlist,
        private AssignStudentToGroupAction $place,
    ) {}

    public function execute(
        string $organizationId,
        string $studentProfileId,
        string $programId,
        string $groupId,
        string $courseId,
        string $actorId,
    ): EnrollmentPlacementData {
        $application = $this->waitlist->execute(
            organizationId: $organizationId,
            studentProfileId: $studentProfileId,
            programId: $programId,
            courseId: $courseId,
            actorId: $actorId,
            reason: __('console_courses.audit.add_existing_student'),
        );

        try {
            return $this->place->execute(
                actorOrganizationId: $organizationId,
                studentProfileId: $studentProfileId,
                programId: $programId,
                groupId: $groupId,
                courseId: $courseId,
                actorId: $actorId,
                reason: __('console_courses.audit.add_existing_student'),
                applicationId: (string) $application->getKey(),
            );
        } catch (BusinessRuleViolation $exception) {
            // التسكين فشل بعد نجاح وضع الطالب بقائمة الانتظار في معاملة
            // منفصلة. طلب أنشأناه للتو (لا طلب سابق أعيد استخدامه) لا معنى
            // لبقائه معلّقًا في قائمة انتظار عامة لم يطلبها أحد — نلغيه
            // بأرشفة ناعمة بدل تركه يظهر لإداري آخر بلا سياق.
            if ($application->wasRecentlyCreated) {
                $application->delete();
            }

            throw $exception;
        }
    }
}
