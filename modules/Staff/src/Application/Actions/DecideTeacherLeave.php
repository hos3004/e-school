<?php

declare(strict_types=1);

namespace Modules\Staff\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Modules\Staff\Domain\Enums\TeacherLeaveStatus;
use Modules\Staff\Domain\Events\TeacherLeaveDecided;
use Modules\Staff\Domain\Models\TeacherLeave;
use Shared\Support\BusinessRuleViolation;

final readonly class DecideTeacherLeave
{
    public function execute(
        TeacherLeave $leave,
        TeacherLeaveStatus $decision,
        ?string $approverId = null,
    ): TeacherLeave {
        if (!$leave->status->canTransitionTo($decision)) {
            throw BusinessRuleViolation::make(
                'staff.leave_transition_forbidden',
                'staff::errors.leave_transition_forbidden',
                ['from' => $leave->status->label(), 'to' => $decision->label()],
            );
        }

        if ($decision === TeacherLeaveStatus::Cancelled) {
            throw BusinessRuleViolation::make(
                'staff.leave_cancel_via_withdraw',
                'staff::errors.leave_transition_forbidden',
                ['from' => $leave->status->label(), 'to' => $decision->label()],
            );
        }

        DB::transaction(function () use ($leave, $decision, $approverId): void {
            $leave->forceFill([
                'status' => $decision,
                'approved_by' => $approverId,
                'approved_at' => CarbonImmutable::now('UTC'),
            ])->save();
        });

        Event::dispatch(new TeacherLeaveDecided(
            leaveId: $leave->id,
            staffProfileId: $leave->staff_profile_id,
            startsAt: CarbonImmutable::instance($leave->starts_at)->toIso8601String(),
            endsAt: CarbonImmutable::instance($leave->ends_at)->toIso8601String(),
            decision: $decision,
        ));

        return $leave->refresh();
    }
}
