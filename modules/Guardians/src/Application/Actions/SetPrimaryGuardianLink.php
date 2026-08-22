<?php

declare(strict_types=1);

namespace Modules\Guardians\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Guardians\Domain\Events\GuardianLinkUpdated;
use Modules\Guardians\Domain\Models\GuardianLink;
use Shared\Support\BusinessRuleViolation;

/**
 * تعيين رابط كوصي أساسي للطالب — واصي أساسي واحد فقط في كل لحظة.
 */
final readonly class SetPrimaryGuardianLink
{
    public function execute(string $guardianLinkId): GuardianLink
    {
        /** @var GuardianLink $link */
        $link = GuardianLink::query()->findOrFail($guardianLinkId);

        if ($link->is_primary) {
            return $link;
        }

        DB::transaction(function () use ($link): void {
            GuardianLink::query()
                ->forStudent($link->student_profile_id)
                ->primary()
                ->update(['is_primary' => false]);

            $link->forceFill(['is_primary' => true])->save();
        });

        event(new GuardianLinkUpdated(
            guardianLinkId: $link->id,
            guardianProfileId: $link->guardian_profile_id,
            studentProfileId: $link->student_profile_id,
            changes: ['is_primary' => ['old' => false, 'new' => true]],
        ));

        return $link->refresh();
    }
}
