<?php

declare(strict_types=1);

namespace Modules\Guardians\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Guardians\Domain\Events\GuardianProfileArchived;
use Modules\Guardians\Domain\Models\GuardianProfile;

/**
 * أرشفة ملف وصي — تعليق لا حذف.
 *
 * الروابط تبقى في السجل لكن تُسقَط صلاحيات الوساطة عبر مستمع الحدث
 * DeactivateLinksWhenGuardianArchived الذي يعمل بعد نجاح المعاملة.
 */
final readonly class ArchiveGuardianProfile
{
    public function execute(string $guardianProfileId, string $reason): void
    {
        $profile = GuardianProfile::query()->findOrFail($guardianProfileId);

        DB::transaction(function () use ($profile): void {
            $profile->delete();
        });

        event(new GuardianProfileArchived(
            guardianProfileId: $profile->id,
            reason: $reason,
        ));
    }
}
