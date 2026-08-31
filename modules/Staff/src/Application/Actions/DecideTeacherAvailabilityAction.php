<?php

declare(strict_types=1);

namespace Modules\Staff\Application\Actions;

use Illuminate\Contracts\Events\Dispatcher;
use Modules\Audit\Domain\Contracts\AuditRecorder;
use Modules\Staff\Domain\Enums\TeacherAvailabilityApprovalStatus;
use Modules\Staff\Domain\Events\TeacherAvailabilityApproved;
use Modules\Staff\Domain\Models\StaffProfile;
use Modules\Staff\Domain\Models\TeacherAvailability;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;

final readonly class DecideTeacherAvailabilityAction
{
    public function __construct(
        private Dispatcher $events,
        private AuditRecorder $audit,
        private Transaction $transaction,
    ) {}

    public function execute(
        TeacherAvailability $availability,
        TeacherAvailabilityApprovalStatus $decision,
        string $actorId,
        string $reason,
    ): TeacherAvailability {
        if (!in_array($decision, [
            TeacherAvailabilityApprovalStatus::Approved,
            TeacherAvailabilityApprovalStatus::Rejected,
        ], true)) {
            throw BusinessRuleViolation::make(
                'staff.availability_decision_invalid',
                'staff::errors.availability_decision_invalid',
            );
        }

        /** @var array{0: TeacherAvailability, 1: StaffProfile, 2: bool} $result */
        $result = $this->transaction->run(function () use ($availability, $decision, $actorId, $reason): array {
            /** @var TeacherAvailability $locked */
            $locked = TeacherAvailability::query()->lockForUpdate()->findOrFail($availability->getKey());

            /** @var StaffProfile $profile */
            $profile = StaffProfile::query()->findOrFail($locked->staff_profile_id);

            if ($locked->approval_status === $decision) {
                return [$locked, $profile, false];
            }

            if (!$locked->approval_status->canTransitionTo($decision)) {
                throw BusinessRuleViolation::make(
                    'staff.availability_invalid_approval_transition',
                    'staff::errors.availability_invalid_approval_transition',
                );
            }

            $oldStatus = $locked->approval_status->value;
            $locked->approval_status = $decision;
            $locked->decided_by = $actorId;
            $locked->decided_at = now()->utc();
            $locked->decision_reason = $reason;

            if ($decision === TeacherAvailabilityApprovalStatus::Approved) {
                $locked->approved_by = $actorId;
                $locked->approved_at = $locked->decided_at;
            }

            $locked->save();

            $this->audit->record(
                organizationId: (string) $profile->organization_id,
                actorId: $actorId,
                actorType: 'user',
                action: 'staff.availability_decided',
                auditableType: 'teacher_availability',
                auditableId: (string) $locked->getKey(),
                oldValues: ['approval_status' => $oldStatus],
                newValues: ['approval_status' => $decision->value],
                reason: $reason,
            );

            return [$locked, $profile, true];
        });

        [$decided, $profile, $changed] = $result;

        if ($changed && $decision === TeacherAvailabilityApprovalStatus::Approved) {
            $this->events->dispatch(new TeacherAvailabilityApproved(
                staffProfileId: (string) $profile->id,
                teacherUserId: (string) $profile->user_id,
                organizationId: (string) $profile->organization_id,
                availabilityId: (string) $decided->id,
                actorId: $actorId,
            ));
        }

        return $decided;
    }
}
