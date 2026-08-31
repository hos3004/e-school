<?php

declare(strict_types=1);

namespace Modules\Groups\Application\Policies;

use Modules\Groups\Domain\Models\GroupTeacher;

/**
 * سياسة إسناد المعلمين للمجموعات.
 */
final class GroupTeacherPolicy
{
    public function viewAny(mixed $user): bool
    {
        return $user->can('group.view');
    }

    public function view(mixed $user, GroupTeacher $assignment): bool
    {
        return $user->can('group.view') && $this->sameOrganization($user, $assignment);
    }

    public function create(mixed $user): bool
    {
        return $user->can('group.manage');
    }

    public function update(mixed $user, GroupTeacher $assignment): bool
    {
        return $user->can('group.manage') && $this->sameOrganization($user, $assignment);
    }

    /** إلغاء الإسناد — تثبيت تاريخ النهاية دون حذف السجل. */
    public function delete(mixed $user, GroupTeacher $assignment): bool
    {
        return $user->can('group.manage') && $this->sameOrganization($user, $assignment);
    }

    private function sameOrganization(mixed $user, GroupTeacher $assignment): bool
    {
        $organizationId = data_get($user, 'organization_id');
        $group = $assignment->group;

        return is_string($organizationId)
            && $organizationId !== ''
            && $group !== null
            && hash_equals($organizationId, (string) $group->organization_id);
    }
}
