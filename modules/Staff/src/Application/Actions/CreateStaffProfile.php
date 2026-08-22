<?php

declare(strict_types=1);

namespace Modules\Staff\Application\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Modules\Staff\Domain\Enums\EmploymentType;
use Modules\Staff\Domain\Events\StaffProfileCreated;
use Modules\Staff\Domain\Models\StaffProfile;
use Shared\Support\BusinessRuleViolation;

final readonly class CreateStaffProfile
{
    /**
     * @param array<string, mixed>|null $bio
     * @param list<string>|null $specializations
     */
    public function execute(
        string $organizationId,
        string $userId,
        string $staffCode,
        EmploymentType $employmentType,
        ?string $hiredAt = null,
        ?array $bio = null,
        ?array $specializations = null,
    ): StaffProfile {
        $existing = StaffProfile::query()->forUser($userId)->exists();

        if ($existing) {
            throw BusinessRuleViolation::make(
                'staff.profile_already_exists',
                'staff::errors.profile_already_exists',
                ['user_id' => $userId],
            );
        }

        $profile = DB::transaction(function () use ($organizationId, $userId, $staffCode, $employmentType, $hiredAt, $bio, $specializations): StaffProfile {
            return StaffProfile::query()->create([
                'organization_id' => $organizationId,
                'user_id' => $userId,
                'staff_code' => $staffCode,
                'employment_type' => $employmentType,
                'hired_at' => $hiredAt,
                'bio' => $bio,
                'specializations' => $specializations,
            ]);
        });

        Event::dispatch(new StaffProfileCreated(
            staffProfileId: $profile->id,
            organizationId: $profile->organization_id,
            userId: $profile->user_id,
            staffCode: $profile->staff_code,
            employmentType: $profile->employment_type,
        ));

        return $profile;
    }
}
