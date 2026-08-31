<?php

declare(strict_types=1);

namespace Modules\Groups\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Modules\Audit\Domain\Contracts\AuditRecorder;
use Modules\Groups\Domain\Enums\MembershipStatus;
use Modules\Groups\Domain\Events\StudentLeftGroup;
use Modules\Groups\Domain\Models\Group;
use Modules\Groups\Domain\Models\GroupMembership;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;

/**
 * خروج طالب من مجموعة: تثبيت وقت المغادرة وانتقال الحالة عبر canTransitionTo
 * دون حذف سجل الانتساب.
 */
final readonly class WithdrawStudentAction
{
    public function __construct(
        private Transaction $transaction,
        private Dispatcher $events,
        private AuditRecorder $audit,
    ) {}

    public function execute(GroupMembership $membership, string $reason, ?string $actorId = null): GroupMembership
    {
        $this->assertReasonGiven($reason);
        $this->assertStillActive($membership);

        /** @var Group $group */
        $group = $membership->group()->firstOrFail();

        $membership = $this->transaction->run(function () use ($membership, $group, $reason, $actorId): GroupMembership {
            $membership->left_at = CarbonImmutable::now('UTC');
            $membership->status = MembershipStatus::Left;
            $membership->save();

            if ($actorId !== null) {
                $this->audit->record(
                    organizationId: (string) $group->organization_id,
                    actorId: $actorId,
                    actorType: 'user',
                    action: 'groups.student_withdrawn',
                    auditableType: 'group_memberships',
                    auditableId: (string) $membership->getKey(),
                    oldValues: ['status' => MembershipStatus::Active->value, 'left_at' => null],
                    newValues: [
                        'status' => MembershipStatus::Left->value,
                        'left_at' => $membership->left_at->toIso8601String(),
                    ],
                    reason: trim($reason),
                );
            }

            return $membership;
        });

        $this->events->dispatch(new StudentLeftGroup(
            membershipId: (string) $membership->getKey(),
            groupId: (string) $membership->group_id,
            organizationId: (string) $group->organization_id,
            studentProfileId: (string) $membership->student_profile_id,
            reason: trim($reason),
        ));

        return $membership;
    }

    private function assertReasonGiven(string $reason): void
    {
        if (trim($reason) === '') {
            throw BusinessRuleViolation::make(
                'groups.reason_required',
                'groups::errors.withdraw_reason_required',
            );
        }
    }

    private function assertStillActive(GroupMembership $membership): void
    {
        if ($membership->left_at !== null || !$membership->status->canTransitionTo(MembershipStatus::Left)) {
            throw BusinessRuleViolation::make(
                'groups.membership_not_active',
                'groups::errors.membership_not_active',
                ['membership_id' => (string) $membership->getKey()],
            );
        }
    }
}
