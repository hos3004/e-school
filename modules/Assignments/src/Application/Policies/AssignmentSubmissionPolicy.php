<?php

declare(strict_types=1);

namespace Modules\Assignments\Application\Policies;

use Modules\Assignments\Domain\Contracts\AssignmentAudienceQueries;
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
    public function __construct(
        private readonly AssignmentAudienceQueries $audiences,
    ) {}

    /** @param  mixed  $user */
    public function viewAny($user): bool
    {
        return $user !== null && ($user->can('assignment.manage') || $user->can('assignment.grade'));
    }

    /** @param  mixed  $user */
    public function view($user, AssignmentSubmission $submission): bool
    {
        if ($user === null) {
            return false;
        }

        $assignment = $submission->assignment()->first();

        if ($assignment === null || (string) $user->organization_id !== (string) $assignment->organization_id) {
            return false;
        }

        $audience = $this->audiences->forUser(
            (string) $assignment->organization_id,
            (string) $user->getAuthIdentifier(),
        );

        if ($user->can('assignment.submit')
            && $audience->studentProfileId === (string) $submission->student_profile_id) {
            return true;
        }

        return ($user->can('assignment.manage') || $user->can('assignment.grade'))
            && ($audience->staffProfileId === (string) $assignment->staff_profile_id
                || $user->can('settings.manage')
                || $user->can('student.update')
                || $user->can('message.moderate'));
    }

    /** @param  mixed  $user */
    public function create($user): bool
    {
        return $user !== null && $user->can('assignment.submit');
    }

    /** التسليم لا يُعدَّل مباشرة — التعديل الوحيد هو إجراء Submit. */
    public function update(mixed $user, AssignmentSubmission $submission): bool
    {
        return false;
    }

    /** التسليم لا يُحذف أبدًا. */
    public function delete(mixed $user, AssignmentSubmission $submission): bool
    {
        return false;
    }

    /** رصد الدرجة — فعل المعلم بصلاحية مستقلة موثّقة. */
    public function grade(mixed $user, AssignmentSubmission $submission): bool
    {
        if ($user === null || !$user->can('assignment.grade')) {
            return false;
        }

        return $this->view($user, $submission);
    }
}
