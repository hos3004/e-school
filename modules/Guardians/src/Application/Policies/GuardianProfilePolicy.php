<?php

declare(strict_types=1);

namespace Modules\Guardians\Application\Policies;

use Modules\Guardians\Domain\Models\GuardianProfile;

/**
 * سياسة ملف الوصي.
 *
 * لا فحص لأسماء الأدوار أبدًا — صلاحيات البوابة أو مقارنة الملكية فقط.
 */
final class GuardianProfilePolicy
{
    public function viewAny($user): bool
    {
        return $user->can('guardians.view_any');
    }

    public function view($user, GuardianProfile $profile): bool
    {
        return $user->can('guardians.view_any')
            || $profile->user_id === $user->id;
    }

    public function create($user): bool
    {
        return $user->can('guardians.create');
    }

    public function update($user, GuardianProfile $profile): bool
    {
        return $user->can('guardians.update_any')
            || $profile->user_id === $user->id;
    }

    public function delete($user, GuardianProfile $profile): bool
    {
        return $user->can('guardians.archive_any');
    }

    public function linkStudents($user, GuardianProfile $profile): bool
    {
        return $user->can('guardians.link_any')
            || $profile->user_id === $user->id;
    }
}
