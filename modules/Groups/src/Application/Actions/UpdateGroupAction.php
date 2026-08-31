<?php

declare(strict_types=1);

namespace Modules\Groups\Application\Actions;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Arr;
use Modules\Audit\Domain\Contracts\AuditRecorder;
use Modules\Groups\Domain\Events\GroupUpdated;
use Modules\Groups\Domain\Models\Group;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;

/**
 * تحديث بيانات مجموعة قائمة: الحالة والهوية التنظيمية لا تُعدَّل من هنا —
 * تغيير الحالة يتم عبر إجراءات مخصّصة (تفعيل/إتمام).
 */
final readonly class UpdateGroupAction
{
    /** الحقول التي لا يجوز تعديلها عبر هذا الإجراء. */
    private const PROTECTED_FIELDS = ['organization_id', 'status', 'reason'];

    public function __construct(
        private Transaction $transaction,
        private Dispatcher $events,
        private AuditRecorder $audit,
    ) {}

    /**
     * @param array<string, mixed> $data بيانات التعديل بعد تحقّق FormRequest
     */
    public function execute(Group $group, array $data, ?string $actorId = null, ?string $reason = null): Group
    {
        $this->assertNotArchived($group);
        $this->assertEndsAfterStarts($group, $data);

        $data = Arr::except($data, self::PROTECTED_FIELDS);
        $updatedFields = array_keys($data);
        $oldValues = Arr::only($group->getAttributes(), $updatedFields);

        $group = $this->transaction->run(function () use ($group, $data, $updatedFields, $oldValues, $actorId, $reason): Group {
            $group->fill($data);
            $group->save();

            if ($updatedFields !== [] && $actorId !== null && $reason !== null && trim($reason) !== '') {
                $this->audit->record(
                    organizationId: (string) $group->organization_id,
                    actorId: $actorId,
                    actorType: 'user',
                    action: 'groups.group_updated',
                    auditableType: 'groups',
                    auditableId: (string) $group->getKey(),
                    oldValues: $oldValues,
                    newValues: Arr::only($group->getAttributes(), $updatedFields),
                    reason: trim($reason),
                );
            }

            return $group;
        });

        if ($updatedFields !== []) {
            $this->events->dispatch(new GroupUpdated(
                groupId: (string) $group->getKey(),
                organizationId: (string) $group->organization_id,
                updatedFields: $updatedFields,
            ));
        }

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

    /**
     * @param array<string, mixed> $data
     */
    private function assertEndsAfterStarts(Group $group, array $data): void
    {
        $startsOn = isset($data['starts_on'])
            ? (string) $data['starts_on']
            : (string) ($group->starts_on?->toDateString() ?? '');

        $endsOn = array_key_exists('ends_on', $data)
            ? $data['ends_on']
            : $group->ends_on?->toDateString();

        if ($startsOn === '' || $endsOn === null || (string) $endsOn === '') {
            return;
        }

        if ((string) $endsOn < $startsOn) {
            throw BusinessRuleViolation::make(
                'groups.ends_before_starts',
                'groups::errors.ends_before_starts',
                ['starts_on' => $startsOn, 'ends_on' => $endsOn],
            );
        }
    }
}
