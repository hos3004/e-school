<?php

declare(strict_types=1);

namespace Modules\Groups\Application\Actions;

use Carbon\CarbonImmutable;
use Shared\Support\Transaction;
use Illuminate\Contracts\Events\Dispatcher;
use Modules\Groups\Domain\Enums\MembershipStatus;
use Modules\Groups\Domain\Events\StudentEnrolledInGroup;
use Modules\Groups\Domain\Models\GroupMembership;
use Modules\Groups\Domain\Models\Group;
use Shared\Support\BusinessRuleViolation;

/**
 * تسجيل طالب في مجموعة: يتحقق من حالة المجموعة والسعة المتاحة
 * وعدم وجود انتساب نشط سابق، ثم ينشئ قيد الانتساب وينشر الحدث.
 */
final readonly class EnrollStudentAction
{
    public function __construct(
        private Transaction $transaction,
        private Dispatcher $events,
    ) {}

    public function execute(Group $group, string $studentProfileId): GroupMembership
    {
        $this->assertNotArchived($group);
        $this->assertAcceptsMembers($group);
        $this->assertCapacityAvailable($group);
        $this->assertNotAlreadyEnrolled($group, $studentProfileId);

        $membership = $this->transaction->run(function () use ($group, $studentProfileId): GroupMembership {
            $membership = new GroupMembership;
            $membership->fill([
                'group_id' => (string) $group->getKey(),
                'student_profile_id' => $studentProfileId,
                'joined_at' => CarbonImmutable::now('UTC'),
                'status' => MembershipStatus::Active,
            ]);
            $membership->save();

            return $membership;
        });

        $this->events->dispatch(new StudentEnrolledInGroup(
            membershipId: (string) $membership->getKey(),
            groupId: (string) $group->getKey(),
            organizationId: (string) $group->organization_id,
            studentProfileId: $studentProfileId,
        ));

        return $membership;
    }

    private function assertNotArchived(Group $group): void
    {
        if ($group->trashed()) {
            throw BusinessRuleViolation::make(
                'groups.already_archived',
                'groups::errors.already_archived',
                ['group_id' => (string) $group->getKey()],
            );
        }
    }

    private function assertAcceptsMembers(Group $group): void
    {
        if (! $group->status->acceptsEnrollment()) {
            throw BusinessRuleViolation::make(
                'groups.group_not_open',
                'groups::errors.group_not_accepting_members',
                ['status' => $group->status->label()],
            );
        }
    }

    private function assertCapacityAvailable(Group $group): void
    {
        $activeCount = GroupMembership::query()
            ->where('group_id', (string) $group->getKey())
            ->whereNull('left_at')
            ->count();

        if ($activeCount >= $group->capacity) {
            throw BusinessRuleViolation::make(
                'groups.capacity_reached',
                'groups::errors.capacity_reached',
                ['capacity' => $group->capacity],
            );
        }
    }

    private function assertNotAlreadyEnrolled(Group $group, string $studentProfileId): void
    {
        $exists = GroupMembership::query()
            ->where('group_id', (string) $group->getKey())
            ->where('student_profile_id', $studentProfileId)
            ->whereNull('left_at')
            ->exists();

        if ($exists) {
            throw BusinessRuleViolation::make(
                'groups.already_enrolled',
                'groups::errors.already_enrolled',
                ['student_profile_id' => $studentProfileId],
            );
        }
    }
}
