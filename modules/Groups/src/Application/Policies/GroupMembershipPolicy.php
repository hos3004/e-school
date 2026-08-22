<?php

declare(strict_types=1);

namespace Modules\Groups\Application\Policies;

use Modules\Groups\Domain\Models\GroupMembership;

/**
 * سياسة انتساب الطلاب للمجموعات.
 */
final class GroupMembershipPolicy
{
    public function viewAny($user): bool
    {
        return $user->can('groups.view_any');
    }

    public function view($user, GroupMembership $membership): bool
    {
        return $user->can('groups.view_any');
    }

    public function create($user): bool
    {
        return $user->can('groups.enroll_student');
    }

    public function update($user, GroupMembership $membership): bool
    {
        return $user->can('groups.update_any');
    }

    /** خروج الطالب من المجموعة — لا حذف للسجل أبدًا. */
    public function delete($user, GroupMembership $membership): bool
    {
        return $user->can('groups.withdraw_student');
    }
}
