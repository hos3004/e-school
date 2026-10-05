<?php

declare(strict_types=1);

namespace Modules\Staff\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Audit\Domain\Contracts\AuditRecorder;
use Modules\Staff\Domain\Enums\TeacherAvailabilityApprovalStatus;
use Modules\Staff\Domain\Models\StaffProfile;
use Modules\Staff\Domain\Models\TeacherAvailability;

final readonly class ActivatePendingTeacherAvailability
{
    public function __construct(private AuditRecorder $audit) {}

    public function execute(string $organizationId, string $availabilityId): bool
    {
        if ((bool) config('scheduling.availability.teacher_requires_approval')) {
            return false;
        }

        return DB::transaction(function () use ($organizationId, $availabilityId): bool {
            $slot = TeacherAvailability::query()
                ->whereIn('staff_profile_id', StaffProfile::query()->forOrganization($organizationId)->select('id'))
                ->lockForUpdate()->find($availabilityId);

            if (!$slot instanceof TeacherAvailability
                || $slot->approval_status !== TeacherAvailabilityApprovalStatus::Pending
                || !$slot->approval_status->canTransitionTo(TeacherAvailabilityApprovalStatus::Approved)) {
                return false;
            }

            $slot->approval_status = TeacherAvailabilityApprovalStatus::Approved;
            $slot->approved_at = now()->utc();
            $slot->decision_reason = __('staff::availability.immediate_activation_reason');
            $slot->save();

            $this->audit->record(
                organizationId: $organizationId,
                actorId: null,
                actorType: 'system',
                action: 'staff.availability_activated',
                auditableType: 'teacher_availability',
                auditableId: $availabilityId,
                oldValues: ['approval_status' => TeacherAvailabilityApprovalStatus::Pending->value],
                newValues: ['approval_status' => TeacherAvailabilityApprovalStatus::Approved->value, 'activation_mode' => 'immediate'],
                reason: __('staff::availability.immediate_activation_reason'),
            );

            return true;
        });
    }
}
