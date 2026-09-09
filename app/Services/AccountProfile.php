<?php

declare(strict_types=1);

namespace App\Services;

use Modules\Identity\Domain\Models\User;
use Modules\Staff\Domain\Models\StaffProfile;
use Modules\Students\Domain\Models\StudentProfile;

final class AccountProfile
{
    public function student(User $user): ?StudentProfile
    {
        if (!$user->organization_id) {
            return null;
        }

        return StudentProfile::query()->forOrganization($user->organization_id)->where('user_id', $user->id)->first();
    }

    public function teacher(User $user): ?StaffProfile
    {
        if (!$user->organization_id) {
            return null;
        }

        return StaffProfile::query()->forOrganization($user->organization_id)->forUser($user->id)->first();
    }

    public function exists(User $user): bool
    {
        return $this->student($user) !== null || $this->teacher($user) !== null;
    }
}
