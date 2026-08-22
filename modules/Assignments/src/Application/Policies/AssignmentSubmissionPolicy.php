<?php

declare(strict_types=1);

namespace Modules\Assignments\Application\Policies;

use Modules\Assignments\Domain\Models\AssignmentSubmission;

/**
 * سياسة تسليمات الطلاب.
 *
 * لا فحص لأدوار — صلاحيات معلنة فقط. الرصد بصلاحية مستقلة،
 * ولا حذف للتسليمات أبدًا.
 * ملاحظة الحدود: تضييق «تسليمات الطالب نفسه» يحدث في طبقة القراءة
 * عبر student_profile_id المخزَّن على الصف، لأن ملفات الطلاب
 * ملك لموديول آخر ولا يجوز لهذه السياسة استيراد نماذجها.
 */
final class AssignmentSubmissionPolicy
{
    /** @param  mixed  $user */
    public function viewAny($user): bool
    {
        return $user !== null && $user->can('assignments.submissions.view_any');
    }

    /** @param  mixed  $user */
    public function view($user, AssignmentSubmission $submission): bool
    {
        if ($user === null) {
            return false;
        }

        return $user->can('assignments.submissions.view_any')
            || $user->can('assignments.submissions.view_own');
    }

    /** @param  mixed  $user */
    public function create($user): bool
    {
        return $user !== null && $user->can('assignments.submissions.submit');
    }

    /** التسليم لا يُعدَّل مباشرة — التعديل الوحيد هو إجراء Submit. */
    public function update($user, AssignmentSubmission $submission): bool
    {
        return false;
    }

    /** التسليم لا يُحذف أبدًا. */
    public function delete($user, AssignmentSubmission $submission): bool
    {
        return false;
    }

    /** رصد الدرجة — فعل المعلم بصلاحية مستقلة موثّقة. */
    public function grade($user, AssignmentSubmission $submission): bool
    {
        return $user !== null && $user->can('assignments.submissions.grade');
    }
}
