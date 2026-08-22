<?php

declare(strict_types=1);

namespace Modules\Guardians\Application\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;
use Modules\Guardians\Domain\Models\GuardianLink;

/**
 * سياسة رابط الوصي بالطالب.
 */
final class GuardianLinkPolicy
{
    public function viewAny(Authenticatable&Authorizable $user): bool
    {
        return $user->can('guardians.view_any');
    }

    public function view(Authenticatable&Authorizable $user, GuardianLink $link): bool
    {
        return $user->can('guardians.view_any')
            || $link->guardian->user_id === (string) $user->getAuthIdentifier();
    }

    public function create(Authenticatable&Authorizable $user): bool
    {
        return $user->can('guardians.link_any');
    }

    public function update(Authenticatable&Authorizable $user, GuardianLink $link): bool
    {
        return $user->can('guardians.link_any');
    }

    public function delete(Authenticatable&Authorizable $user, GuardianLink $link): bool
    {
        return $user->can('guardians.unlink_any');
    }

    /** توثيق الرابط — للإدارة فقط. */
    public function verify(Authenticatable&Authorizable $user, GuardianLink $link): bool
    {
        return $user->can('guardians.verify_any');
    }

    /** تعيين واصي أساسي — للإدارة فقط. */
    public function setPrimary(Authenticatable&Authorizable $user, GuardianLink $link): bool
    {
        return $user->can('guardians.link_any');
    }
}
