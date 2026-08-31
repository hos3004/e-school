<?php

declare(strict_types=1);

namespace Modules\Guardians\Application\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;
use Modules\Guardians\Domain\Models\GuardianProfile;

/**
 * سياسة ملف الوصي.
 *
 * لا فحص لأسماء الأدوار أبدًا — صلاحيات البوابة أو مقارنة الملكية فقط.
 */
final class GuardianProfilePolicy
{
    public function viewAny(Authenticatable&Authorizable $user): bool
    {
        return $user->can('guardian.view');
    }

    public function view(Authenticatable&Authorizable $user, GuardianProfile $profile): bool
    {
        return $this->sameOrganization($user, $profile)
            && ($user->can('guardian.view')
                || $profile->user_id === (string) $user->getAuthIdentifier());
    }

    public function create(Authenticatable&Authorizable $user): bool
    {
        return $user->can('guardian.link');
    }

    public function update(Authenticatable&Authorizable $user, GuardianProfile $profile): bool
    {
        return $this->sameOrganization($user, $profile)
            && ($user->can('guardian.link')
                || $profile->user_id === (string) $user->getAuthIdentifier());
    }

    public function delete(Authenticatable&Authorizable $user, GuardianProfile $profile): bool
    {
        return $this->sameOrganization($user, $profile)
            && $user->can('guardian.link');
    }

    public function linkStudents(Authenticatable&Authorizable $user, GuardianProfile $profile): bool
    {
        return $this->sameOrganization($user, $profile)
            && $user->can('guardian.link');
    }

    private function sameOrganization(Authenticatable&Authorizable $user, GuardianProfile $profile): bool
    {
        $organizationId = data_get($user, 'organization_id');

        return is_string($organizationId)
            && hash_equals($organizationId, (string) $profile->organization_id);
    }
}
