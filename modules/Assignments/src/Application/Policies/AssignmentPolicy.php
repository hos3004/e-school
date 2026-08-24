<?php

declare(strict_types=1);

namespace Modules\Assignments\Application\Policies;

use Modules\Assignments\Domain\Contracts\AssignmentAudienceQueries;
use Modules\Assignments\Domain\Models\Assignment;

/**
 * سياسة الأنشطة.
 *
 * لا فحص لأدوار — صلاحيات معلنة فقط، مع حصر القراءة على نطاق
 * المؤسسة، والحذف حذفًا ناعمًا لا يجوز بعد وجود تسليمات مرصودة.
 * ملاحظة الحدود: التحقق من «معلم النشاط» يتم داخل إجراءات التطبيق
 * عبر المعرّف المخزّن، لأن ملفات الموظفين ملك لموديول آخر.
 */
final class AssignmentPolicy
{
    public function __construct(
        private readonly AssignmentAudienceQueries $audiences,
    ) {}

    /** @param  mixed  $user */
    public function viewAny($user): bool
    {
        return $user !== null && ($user->can('assignment.manage') || $user->can('assignment.submit') || $user->can('assignment.grade'));
    }

    /** @param  mixed  $user */
    public function view($user, Assignment $assignment): bool
    {
        if ($user === null || (string) $user->organization_id !== (string) $assignment->organization_id) {
            return false;
        }

        if ($this->canManageEveryAssignment($user)) {
            return true;
        }

        $audience = $this->audiences->forUser(
            (string) $assignment->organization_id,
            (string) $user->getAuthIdentifier(),
        );

        if (($user->can('assignment.manage') || $user->can('assignment.grade'))
            && $audience->staffProfileId === (string) $assignment->staff_profile_id) {
            return true;
        }

        return $user->can('assignment.submit')
            && $audience->targetsStudent(
                (string) $assignment->course_id,
                $assignment->group_id === null ? null : (string) $assignment->group_id,
            );
    }

    /** @param  mixed  $user */
    public function create($user): bool
    {
        return $user !== null && $user->can('assignment.manage');
    }

    /** @param  mixed  $user */
    public function update($user, Assignment $assignment): bool
    {
        if ($user === null
            || !$user->can('assignment.manage')
            || (string) $user->organization_id !== (string) $assignment->organization_id) {
            return false;
        }

        if ($this->canManageEveryAssignment($user)) {
            return true;
        }

        return $this->audiences->forUser(
            (string) $assignment->organization_id,
            (string) $user->getAuthIdentifier(),
        )->staffProfileId === (string) $assignment->staff_profile_id;
    }

    /** حذف ناعم فقط — ولا يجوز بعد وجود تسليمات مرصودة. */
    public function delete($user, Assignment $assignment): bool
    {
        if (!$this->update($user, $assignment)) {
            return false;
        }

        return !$assignment->submissions()->graded()->exists();
    }

    private function canManageEveryAssignment(mixed $user): bool
    {
        return $user->can('assignment.manage')
            && ($user->can('settings.manage')
                || $user->can('student.update')
                || $user->can('message.moderate'));
    }
}
