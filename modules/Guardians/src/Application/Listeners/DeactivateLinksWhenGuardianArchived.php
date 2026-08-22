<?php

declare(strict_types=1);

namespace Modules\Guardians\Application\Listeners;

use Illuminate\Support\Facades\DB;
use Modules\Guardians\Domain\Events\GuardianProfileArchived;

/**
 * عند أرشفة وصي تُسقَط صلاحيات وساطته على كل روابطه.
 *
 * يعمل بعد نجاح معاملة الأرشفة، وهو idempotent فيمكن إعادة تشغيله بأمان.
 */
final class DeactivateLinksWhenGuardianArchived
{
    public function handle(GuardianProfileArchived $event): void
    {
        DB::table('guardian_links')
            ->where('guardian_profile_id', $event->guardianProfileId)
            ->where(function ($query): void {
                $query->where('can_act_for', true)
                    ->orWhere('is_primary', true);
            })
            ->update([
                'can_act_for' => false,
                'is_primary' => false,
                'updated_at' => now()->toIso8601String(),
            ]);
    }
}
