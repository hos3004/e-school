<?php

declare(strict_types=1);

namespace Modules\Groups\Application\Policies;

use Modules\Groups\Domain\Models\Group;

/**
 * سياسة المجموعات.
 *
 * لا فحص لأسماء الأدوار هنا — القرار دائمًا عبر صلاحية معلنة
 * $user->can('groups.action') حسب مصفوفة الصلاحيات.
 */
final class GroupPolicy
{
    public function viewAny(mixed $user): bool
    {
        return $user->can('group.view');
    }

    public function view(mixed $user, Group $group): bool
    {
        return $user->can('group.view') && $this->sameOrganization($user, $group);
    }

    public function create(mixed $user): bool
    {
        return $user->can('group.manage');
    }

    public function update(mixed $user, Group $group): bool
    {
        return $user->can('group.manage') && $this->sameOrganization($user, $group);
    }

    /** أرشفة مجموعة — إجراء حسّاس للمؤسسة. */
    public function delete(mixed $user, Group $group): bool
    {
        return $user->can('group.manage') && $this->sameOrganization($user, $group);
    }

    public function restore(mixed $user, Group $group): bool
    {
        return $user->can('group.manage') && $this->sameOrganization($user, $group);
    }

    public function activate(mixed $user, Group $group): bool
    {
        return $user->can('group.manage') && $this->sameOrganization($user, $group);
    }

    public function complete(mixed $user, Group $group): bool
    {
        return $user->can('group.manage') && $this->sameOrganization($user, $group);
    }

    public function enrollStudent(mixed $user, Group $group): bool
    {
        return $user->can('group.manage') && $this->sameOrganization($user, $group);
    }

    public function withdrawStudent(mixed $user, Group $group): bool
    {
        return $user->can('group.manage') && $this->sameOrganization($user, $group);
    }

    public function assignTeacher(mixed $user, Group $group): bool
    {
        return $user->can('group.manage') && $this->sameOrganization($user, $group);
    }

    public function attachProgram(mixed $user, Group $group): bool
    {
        return $user->can('group.manage') && $this->sameOrganization($user, $group);
    }

    private function sameOrganization(mixed $user, Group $group): bool
    {
        $organizationId = data_get($user, 'organization_id');

        return is_string($organizationId)
            && $organizationId !== ''
            && hash_equals($organizationId, (string) $group->organization_id);
    }
}
