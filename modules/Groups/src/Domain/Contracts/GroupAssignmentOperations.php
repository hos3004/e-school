<?php

declare(strict_types=1);

namespace Modules\Groups\Domain\Contracts;

/**
 * عمليات إسناد المعلمين وانتساب الطلاب المعلنة لطبقة التركيب.
 *
 * تحميل نماذج المجموعة يبقى داخل الموديول المالك؛ المتصل يمرر
 * معرفات فقط مع الفاعل والسبب، والعمليات تفوّض للإجراءات الرسمية.
 */
interface GroupAssignmentOperations
{
    /**
     * إسناد معلم إلى مجموعة عبر AssignTeacherAction الرسمي.
     */
    public function assignTeacher(
        string $organizationId,
        string $groupId,
        string $staffProfileId,
        ?string $courseId,
        string $role,
        ?string $assignedFrom,
        ?string $assignedTo,
        string $actorId,
        string $reason,
    ): string;

    /** إنهاء إسناد معلم بسبب مكتوب — السجل لا يُحذف. */
    public function unassignTeacher(
        string $organizationId,
        string $assignmentId,
        string $actorId,
        string $reason,
    ): void;

    /** سحب طالب من مجموعة عبر WithdrawStudentAction الرسمي. */
    public function withdrawStudent(
        string $organizationId,
        string $membershipId,
        string $actorId,
        string $reason,
    ): void;
}
