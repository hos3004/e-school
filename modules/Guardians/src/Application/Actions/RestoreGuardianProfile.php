<?php

declare(strict_types=1);

namespace Modules\Guardians\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Guardians\Domain\Events\GuardianProfileRestored;
use Modules\Guardians\Domain\Models\GuardianProfile;
use Shared\Support\BusinessRuleViolation;

/**
 * استرجاع وصي مؤرشف — إلغاء الأرشفة دون مسّ تاريخه.
 *
 * لا يعيد صلاحيات الوساطة (can_act_for/is_primary) التي أسقطتها الأرشفة
 * تلقائيًا؛ إعادة تفعيلها قرار إداري صريح لكل رابط على حدة، بنفس مبدأ
 * إعادة تفعيل الطالب المجمَّد.
 */
final readonly class RestoreGuardianProfile
{
    public function execute(string $guardianProfileId): GuardianProfile
    {
        /** @var GuardianProfile|null $profile */
        $profile = GuardianProfile::query()->withTrashed()->find($guardianProfileId);

        if ($profile === null) {
            throw BusinessRuleViolation::make(
                'guardians.guardian_not_found',
                'guardians::errors.guardian_not_found',
                ['guardian_profile_id' => $guardianProfileId],
            );
        }

        if (!$profile->trashed()) {
            throw BusinessRuleViolation::make(
                'guardians.not_archived',
                'guardians::errors.not_archived',
                ['guardian_profile_id' => $guardianProfileId],
            );
        }

        DB::transaction(function () use ($profile): void {
            $profile->restore();
        });

        event(new GuardianProfileRestored(
            guardianProfileId: (string) $profile->getKey(),
        ));

        return $profile;
    }
}
