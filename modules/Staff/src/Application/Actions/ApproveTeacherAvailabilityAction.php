<?php

declare(strict_types=1);

namespace Modules\Staff\Application\Actions;

use Illuminate\Contracts\Events\Dispatcher;
use Modules\Staff\Domain\Enums\TeacherAvailabilityApprovalStatus;
use Modules\Staff\Domain\Events\TeacherAvailabilityApproved;
use Modules\Staff\Domain\Models\StaffProfile;
use Modules\Staff\Domain\Models\TeacherAvailability;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;

final readonly class ApproveTeacherAvailabilityAction
{
    public function __construct(
        private Dispatcher $events,
        private Transaction $transaction,
    ) {}

    public function execute(TeacherAvailability $availability, ?string $actorId = null): TeacherAvailability
    {
        /** @var array{0: TeacherAvailability, 1: StaffProfile, 2: bool} $result */
        $result = $this->transaction->run(function () use ($availability, $actorId): array {
            /** @var TeacherAvailability $locked */
            $locked = TeacherAvailability::query()->lockForUpdate()->findOrFail($availability->getKey());

            /** @var StaffProfile $profile */
            $profile = StaffProfile::query()->findOrFail($locked->staff_profile_id);

            if ($locked->approval_status === TeacherAvailabilityApprovalStatus::Approved) {
                return [$locked, $profile, false];
            }

            if (!$locked->approval_status->canTransitionTo(TeacherAvailabilityApprovalStatus::Approved)) {
                throw BusinessRuleViolation::make(
                    'staff.availability_invalid_approval_transition',
                    'staff::errors.availability_invalid_approval_transition',
                );
            }

            $locked->approval_status = TeacherAvailabilityApprovalStatus::Approved;
            $locked->approved_by = $actorId;
            $locked->approved_at = now()->utc();
            $locked->save();

            return [$locked, $profile, true];
        });

        [$availability, $profile, $approved] = $result;

        if ($approved) {
            $this->events->dispatch(new TeacherAvailabilityApproved(
                staffProfileId: (string) $profile->id,
                teacherUserId: (string) $profile->user_id,
                organizationId: (string) $profile->organization_id,
                availabilityId: (string) $availability->id,
                actorId: $actorId,
            ));
        }

        return $availability;
    }
}
