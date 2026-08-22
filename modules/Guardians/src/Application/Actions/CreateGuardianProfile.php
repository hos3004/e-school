<?php

declare(strict_types=1);

namespace Modules\Guardians\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Guardians\Domain\Events\GuardianProfileCreated;
use Modules\Guardians\Domain\Models\GuardianProfile;
use Shared\Support\BusinessRuleViolation;

/**
 * إنشاء ملف وصي جديد داخل مؤسسة.
 *
 * الترتيب الإلزامي: حراس ← معاملة ← نشر الحدث بعد النجاح.
 */
final readonly class CreateGuardianProfile
{
    /**
     * @param  array{
     *     organization_id: string,
     *     user_id: string,
     *     national_id_last4?: ?string,
     *     occupation?: ?string,
     *     preferred_contact_channel?: ?string,
     * } $data
     */
    public function execute(array $data): GuardianProfile
    {
        $existing = GuardianProfile::query()
            ->where('user_id', $data['user_id'])
            ->exists();

        if ($existing) {
            throw BusinessRuleViolation::make(
                'guardians.profile_already_exists',
                'guardians::errors.profile_already_exists',
                ['user_id' => $data['user_id']],
            );
        }

        /** @var GuardianProfile $profile */
        $profile = DB::transaction(function () use ($data): GuardianProfile {
            return GuardianProfile::query()->create([
                'organization_id' => $data['organization_id'],
                'user_id' => $data['user_id'],
                'national_id_last4' => $data['national_id_last4'] ?? null,
                'occupation' => $data['occupation'] ?? null,
                'preferred_contact_channel' => $data['preferred_contact_channel'] ?? null,
            ]);
        });

        event(new GuardianProfileCreated(
            guardianProfileId: $profile->id,
            organizationId: $profile->organization_id,
            userId: $profile->user_id,
        ));

        return $profile;
    }
}
