<?php

declare(strict_types=1);

namespace Modules\Guardians\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Guardians\Domain\Events\GuardianLinkVerified;
use Modules\Guardians\Domain\Models\GuardianLink;
use Shared\Support\BusinessRuleViolation;

/**
 * توثيق رابط وصي — إثبات أن صلة القرابة صحيحة بعد مراجعة الإدارة.
 *
 * التوثيق شرط للوساطة عندما guardians.links.require_verification_for_acting.
 */
final readonly class VerifyGuardianLink
{
    public function execute(string $guardianLinkId): GuardianLink
    {
        /** @var GuardianLink $link */
        $link = GuardianLink::query()->findOrFail($guardianLinkId);

        if ($link->verified_at !== null) {
            throw BusinessRuleViolation::make(
                'guardians.link_already_verified',
                'guardians::errors.link_already_verified',
                ['guardian_link_id' => $guardianLinkId],
            );
        }

        DB::transaction(function () use ($link): void {
            $link->forceFill(['verified_at' => CarbonImmutable::now('UTC')])->save();
        });

        event(new GuardianLinkVerified(
            guardianLinkId: $link->id,
            guardianProfileId: $link->guardian_profile_id,
            studentProfileId: $link->student_profile_id,
        ));

        return $link->refresh();
    }
}
