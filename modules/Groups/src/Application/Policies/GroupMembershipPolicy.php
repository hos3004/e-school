<?php

declare(strict_types=1);

namespace Modules\Groups\Application\Policies;

use Modules\Groups\Domain\Models\GroupMembership;

/**
 * سياسة انتساب الطلاب للمجموعات.
 */
final class GroupMembershipPolicy
{
    public function viewAny(mixed $user): bool
    {
        return $user->can('group.view');
    }

    public function view(mixed $user, GroupMembership $membership): bool
    {
        return $user->can('group.view') && $this->sameOrganization($user, $membership);
    }

    public function create(mixed $user): bool
    {
        return $user->can('group.manage');
    }

    public function update(mixed $user, GroupMembership $membership): bool
    {
        return $user->can('group.manage') && $this->sameOrganization($user, $membership);
    }

    /** خروج الطالب من المجموعة — لا حذف للسجل أبدًا. */
    public function delete(mixed $user, GroupMembership $membership): bool
    {
        return $user->can('group.manage') && $this->sameOrganization($user, $membership);
    }

    private function sameOrganization(mixed $user, GroupMembership $membership): bool
    {
        $organizationId = data_get($user, 'organization_id');
        $group = $membership->group;

        return is_string($organizationId)
            && $organizationId !== ''
            && $group !== null
            && hash_equals($organizationId, (string) $group->organization_id);
    }
}
