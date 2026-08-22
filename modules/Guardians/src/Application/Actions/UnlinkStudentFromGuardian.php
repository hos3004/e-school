<?php

declare(strict_types=1);

namespace Modules\Guardians\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Guardians\Domain\Events\GuardianUnlinkedFromStudent;
use Modules\Guardians\Domain\Models\GuardianLink;

/**
 * فكّ رابط وصي عن طالب — حذف فعلي للرابط (الجدول بلا أرشفة).
 */
final readonly class UnlinkStudentFromGuardian
{
    public function execute(string $guardianLinkId, string $reason): void
    {
        /** @var GuardianLink $link */
        $link = GuardianLink::query()->findOrFail($guardianLinkId);

        DB::transaction(function () use ($link): void {
            $link->delete();
        });

        event(new GuardianUnlinkedFromStudent(
            guardianLinkId: $link->id,
            guardianProfileId: $link->guardian_profile_id,
            studentProfileId: $link->student_profile_id,
            reason: $reason,
        ));
    }
}
