<?php

declare(strict_types=1);

namespace Modules\Groups\Application\Policies;

use Modules\Groups\Domain\Models\GroupProgram;

/**
 * سياسة ربط البرامج بالمجموعات.
 */
final class GroupProgramPolicy
{
    public function viewAny(mixed $user): bool
    {
        return $user->can('group.view');
    }

    public function view(mixed $user, GroupProgram $link): bool
    {
        return $user->can('group.view') && $this->sameOrganization($user, $link);
    }

    public function create(mixed $user): bool
    {
        return $user->can('group.manage');
    }

    public function update(mixed $user, GroupProgram $link): bool
    {
        return $user->can('group.manage') && $this->sameOrganization($user, $link);
    }

    /** فك الربط — إزالة الرابط فقط. */
    public function delete(mixed $user, GroupProgram $link): bool
    {
        return $user->can('group.manage') && $this->sameOrganization($user, $link);
    }

    private function sameOrganization(mixed $user, GroupProgram $link): bool
    {
        $organizationId = data_get($user, 'organization_id');
        $group = $link->group;

        return is_string($organizationId)
            && $organizationId !== ''
            && $group !== null
            && hash_equals($organizationId, (string) $group->organization_id);
    }
}
