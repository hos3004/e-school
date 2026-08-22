<?php

declare(strict_types=1);

namespace Modules\Guardians\Application\Policies;

use Modules\Guardians\Domain\Models\GuardianLink;

/**
 * سياسة رابط الوصي بالطالب.
 */
final class GuardianLinkPolicy
{
    public function viewAny($user): bool
    {
        return $user->can('guardians.view_any');
    }

    public function view($user, GuardianLink $link): bool
    {
        return $user->can('guardians.view_any')
            || $link->guardian?->user_id === $user->id;
    }

    public function create($user): bool
    {
        return $user->can('guardians.link_any');
    }

    public function update($user, GuardianLink $link): bool
    {
        return $user->can('guardians.link_any');
    }

    public function delete($user, GuardianLink $link): bool
    {
        return $user->can('guardians.unlink_any');
    }

    /** توثيق الرابط — للإدارة فقط. */
    public function verify($user, GuardianLink $link): bool
    {
        return $user->can('guardians.verify_any');
    }

    /** تعيين واصي أساسي — للإدارة فقط. */
    public function setPrimary($user, GuardianLink $link): bool
    {
        return $user->can('guardians.link_any');
    }
}
