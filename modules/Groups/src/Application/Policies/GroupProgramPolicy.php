<?php

declare(strict_types=1);

namespace Modules\Groups\Application\Policies;

use Modules\Groups\Domain\Models\GroupProgram;

/**
 * سياسة ربط البرامج بالمجموعات.
 */
final class GroupProgramPolicy
{
    public function viewAny($user): bool
    {
        return $user->can('groups.view_any');
    }

    public function view($user, GroupProgram $link): bool
    {
        return $user->can('groups.view_any');
    }

    public function create($user): bool
    {
        return $user->can('groups.attach_program');
    }

    public function update($user, GroupProgram $link): bool
    {
        return $user->can('groups.attach_program');
    }

    /** فك الربط — إزالة الرابط فقط. */
    public function delete($user, GroupProgram $link): bool
    {
        return $user->can('groups.detach_program');
    }
}
