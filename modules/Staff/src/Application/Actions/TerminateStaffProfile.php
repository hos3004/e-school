<?php

declare(strict_types=1);

namespace Modules\Staff\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Modules\Staff\Domain\Events\StaffProfileTerminated;
use Modules\Staff\Domain\Models\StaffProfile;
use Modules\Staff\Domain\Models\TeacherContract;
use Shared\Support\BusinessRuleViolation;

final readonly class TerminateStaffProfile
{
    public function execute(StaffProfile $profile, ?string $reason = null): StaffProfile
    {
        if (!$profile->isActive()) {
            throw BusinessRuleViolation::make(
                'staff.profile_already_terminated',
                'staff::errors.profile_already_terminated',
                ['staff_code' => $profile->staff_code],
            );
        }

        $hasOpenContract = TeacherContract::query()
            ->forProfile($profile->id)
            ->activeOn(CarbonImmutable::now('UTC'))
            ->exists();

        $terminatedAt = CarbonImmutable::now('UTC');

        DB::transaction(function () use ($profile, $terminatedAt, $hasOpenContract): void {
            if ($hasOpenContract) {
                // إغلاق العقود السارية بنهاية اليوم — لا حذف، بل تحديد نهاية صريحة.
                TeacherContract::query()
                    ->forProfile($profile->id)
                    ->activeOn($terminatedAt)
                    ->whereNull('effective_to')
                    ->update(['effective_to' => $terminatedAt->toDateString()]);
            }

            $profile->forceFill(['terminated_at' => $terminatedAt])->save();
        });

        Event::dispatch(new StaffProfileTerminated(
            staffProfileId: $profile->id,
            organizationId: $profile->organization_id,
            userId: $profile->user_id,
            reason: $reason,
            hadActiveContract: $hasOpenContract,
        ));

        return $profile->refresh();
    }
}
