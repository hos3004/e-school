<?php

declare(strict_types=1);

namespace Modules\Staff\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Modules\Staff\Domain\Enums\TeacherLeaveStatus;
use Modules\Staff\Domain\Events\TeacherLeaveRequested;
use Modules\Staff\Domain\Models\StaffProfile;
use Modules\Staff\Domain\Models\TeacherLeave;
use Shared\Support\BusinessRuleViolation;

final readonly class RequestTeacherLeave
{
    public function execute(
        StaffProfile $profile,
        CarbonImmutable|string $startsAt,
        CarbonImmutable|string $endsAt,
        ?string $reason = null,
    ): TeacherLeave {
        $starts = $startsAt instanceof CarbonImmutable ? $startsAt : CarbonImmutable::parse($startsAt);
        $ends = $endsAt instanceof CarbonImmutable ? $endsAt : CarbonImmutable::parse($endsAt);

        if (!$ends->gt($starts)) {
            throw BusinessRuleViolation::make(
                'staff.leave_period_invalid',
                'staff::errors.leave_period_invalid',
                ['starts_at' => $starts->toDateTimeString(), 'ends_at' => $ends->toDateTimeString()],
            );
        }

        // الإجازات المعتمدة تمنع الجدولة عليها — لذلك لا يجوز طلب إجازة
        // تتقاطع مع إجازة معتمدة سابقًا لنفس المعلم.
        $overlaps = TeacherLeave::query()
            ->forProfile($profile->id)
            ->where('status', TeacherLeaveStatus::Approved)
            ->overlapping($starts, $ends)
            ->exists();

        if ($overlaps) {
            throw BusinessRuleViolation::make(
                'staff.leave_overlaps_approved',
                'staff::errors.leave_overlaps_approved',
                ['staff_code' => $profile->staff_code],
            );
        }

        $leave = DB::transaction(function () use ($profile, $starts, $ends, $reason): TeacherLeave {
            return TeacherLeave::query()->create([
                'staff_profile_id' => $profile->id,
                'starts_at' => $starts,
                'ends_at' => $ends,
                'reason' => $reason,
                'status' => TeacherLeaveStatus::Pending,
            ]);
        });

        Event::dispatch(new TeacherLeaveRequested(
            leaveId: $leave->id,
            staffProfileId: $leave->staff_profile_id,
            startsAt: $starts->toIso8601String(),
            endsAt: $ends->toIso8601String(),
            reason: $leave->reason,
        ));

        return $leave;
    }
}
