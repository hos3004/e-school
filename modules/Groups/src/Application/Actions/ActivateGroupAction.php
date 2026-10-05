<?php

declare(strict_types=1);

namespace Modules\Groups\Application\Actions;

use Illuminate\Contracts\Events\Dispatcher;
use Modules\Audit\Domain\Contracts\AuditRecorder;
use Modules\Groups\Domain\Enums\GroupStatus;
use Modules\Groups\Domain\Enums\MembershipStatus;
use Modules\Groups\Domain\Events\GroupActivated;
use Modules\Groups\Domain\Models\Group;
use Modules\Groups\Domain\Models\GroupMembership;
use Modules\Groups\Domain\Models\GroupTeacher;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;

/**
 * تفعيل مجموعة «قيد التخطيط» لتصبح جاهزة لاستقبال الطلاب والمعلمين.
 *
 * التفعيل هو البوابة التي تُستوفى عندها البيانات المؤجَّلة عند إنشاء المسودة:
 * المعلم والسعة وتاريخ البداية. الشروط سياسة مدرسة تعيش في `config/groups.php`.
 *
 * وهو كذلك اللحظة التي يترقّى فيها الانتساب المعلّق إلى نشط — فالطلاب الذين
 * سُكّنوا في المسودة يصيرون أعضاء فعليين دفعة واحدة داخل المعاملة نفسها.
 */
final readonly class ActivateGroupAction
{
    public function __construct(
        private Transaction $transaction,
        private Dispatcher $events,
        private AuditRecorder $audit,
    ) {}

    public function execute(Group $group, ?string $actorId = null, ?string $reason = null): Group
    {
        $this->assertNotArchived($group);
        $this->assertTransitionAllowed($group);

        $group = $this->transaction->run(function () use ($group, $actorId, $reason): Group {
            /** @var Group $locked */
            $locked = Group::query()->lockForUpdate()->findOrFail($group->getKey());

            $this->assertDataComplete($locked);
            $occupiedSeats = $this->assertCapacityCoversMembers($locked);

            $oldStatus = $locked->status->value;
            $locked->status = GroupStatus::Active;
            $locked->save();

            $promoted = $this->promotePendingMemberships($locked);

            if ($actorId !== null && $reason !== null && trim($reason) !== '') {
                $this->audit->record(
                    organizationId: (string) $locked->organization_id,
                    actorId: $actorId,
                    actorType: 'user',
                    action: 'groups.group_activated',
                    auditableType: 'groups',
                    auditableId: (string) $locked->getKey(),
                    oldValues: ['status' => $oldStatus],
                    newValues: [
                        'status' => GroupStatus::Active->value,
                        'capacity' => $locked->capacity,
                        'occupied_seats' => $occupiedSeats,
                        'promoted_memberships' => $promoted,
                    ],
                    reason: trim($reason),
                );
            }

            $group->setRawAttributes($locked->getAttributes(), true);

            return $locked;
        });

        $this->events->dispatch(new GroupActivated(
            groupId: (string) $group->getKey(),
            organizationId: (string) $group->organization_id,
        ));

        return $group;
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

    private function assertTransitionAllowed(Group $group): void
    {
        if (!$group->status->canTransitionTo(GroupStatus::Active)) {
            throw BusinessRuleViolation::make(
                'groups.invalid_status_transition',
                'groups::errors.invalid_status_transition',
                ['from' => $group->status->label(), 'to' => GroupStatus::Active->label()],
            );
        }
    }

    /** البيانات التي أجّلها إنشاء المسودة يجب أن تكتمل قبل التفعيل. */
    private function assertDataComplete(Group $group): void
    {
        $missing = [];

        if ((bool) config('groups.activation.requires_capacity') && $group->capacity === null) {
            $missing[] = __('groups::attributes.capacity');
        }

        if ((bool) config('groups.activation.requires_start_date') && $group->starts_on === null) {
            $missing[] = __('groups::attributes.starts_on');
        }

        if ((bool) config('groups.activation.requires_teacher') && !$this->hasOpenTeacher($group)) {
            $missing[] = __('groups::attributes.teacher');
        }

        if ($missing !== []) {
            throw BusinessRuleViolation::make(
                'groups.activation_data_incomplete',
                'groups::errors.activation_data_incomplete',
                ['missing' => implode('، ', $missing)],
            );
        }
    }

    /**
     * السعة المعلَنة لا يجوز أن تقل عن عدد من يشغلون مقاعد بالفعل.
     *
     * @return int عدد المقاعد المشغولة
     */
    private function assertCapacityCoversMembers(Group $group): int
    {
        $occupiedSeats = GroupMembership::query()
            ->forGroup((string) $group->getKey())
            ->active()
            ->count();

        if ($group->capacity !== null && $occupiedSeats > $group->capacity) {
            throw BusinessRuleViolation::make(
                'groups.capacity_below_members',
                'groups::errors.capacity_below_members',
                ['capacity' => $group->capacity, 'members' => $occupiedSeats],
            );
        }

        return $occupiedSeats;
    }

    private function hasOpenTeacher(Group $group): bool
    {
        return GroupTeacher::query()
            ->forGroup((string) $group->getKey())
            ->open()
            ->exists();
    }

    /** ترقية الانتسابات المعلّقة إلى نشطة عبر آلة الحالات لا بتعديل مباشر. */
    private function promotePendingMemberships(Group $group): int
    {
        $pending = GroupMembership::query()
            ->forGroup((string) $group->getKey())
            ->where('status', MembershipStatus::Pending)
            ->whereNull('left_at')
            ->lockForUpdate()
            ->get();

        foreach ($pending as $membership) {
            if (!$membership->status->canTransitionTo(MembershipStatus::Active)) {
                throw BusinessRuleViolation::make(
                    'groups.invalid_membership_transition',
                    'groups::errors.invalid_membership_transition',
                    [
                        'from' => $membership->status->label(),
                        'to' => MembershipStatus::Active->label(),
                    ],
                );
            }

            $membership->status = MembershipStatus::Active;
            $membership->save();
        }

        return $pending->count();
    }
}
