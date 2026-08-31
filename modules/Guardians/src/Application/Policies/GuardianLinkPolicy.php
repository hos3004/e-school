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
        return $user->can('guardian.view');
    }

    public function view(Authenticatable&Authorizable $user, GuardianLink $link): bool
    {
        return $this->sameOrganization($user, $link)
            && ($user->can('guardian.view')
                || $link->guardian->user_id === (string) $user->getAuthIdentifier());
    }

    public function create(Authenticatable&Authorizable $user): bool
    {
        return $user->can('guardian.link');
    }

    public function update(Authenticatable&Authorizable $user, GuardianLink $link): bool
    {
        return $this->sameOrganization($user, $link)
            && $user->can('guardian.link');
    }

    public function delete(Authenticatable&Authorizable $user, GuardianLink $link): bool
    {
        return $this->sameOrganization($user, $link)
            && $user->can('guardian.link');
    }

    /** توثيق الرابط — للإدارة فقط. */
    public function verify(Authenticatable&Authorizable $user, GuardianLink $link): bool
    {
        return $this->sameOrganization($user, $link)
            && $user->can('guardian.link');
    }

    /** تعيين واصي أساسي — للإدارة فقط. */
    public function setPrimary(Authenticatable&Authorizable $user, GuardianLink $link): bool
    {
        return $this->sameOrganization($user, $link)
            && $user->can('guardian.link');
    }

    private function sameOrganization(Authenticatable&Authorizable $user, GuardianLink $link): bool
    {
        $organizationId = data_get($user, 'organization_id');

        return is_string($organizationId)
            && hash_equals($organizationId, (string) $link->guardian->organization_id);
    }
}
