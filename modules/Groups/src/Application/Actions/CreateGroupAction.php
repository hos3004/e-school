<?php

declare(strict_types=1);

namespace Modules\Groups\Application\Actions;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Arr;
use Modules\Audit\Domain\Contracts\AuditRecorder;
use Modules\Groups\Domain\Enums\GroupStatus;
use Modules\Groups\Domain\Events\GroupCreated;
use Modules\Groups\Domain\Models\Group;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;

/**
 * إنشاء مجموعة جديدة داخل مؤسسة: تبدأ بحالة «قيد التخطيط»
 * ويُنشر حدث الإنشاء بعد نجاح الحفظ.
 */
final readonly class CreateGroupAction
{
    public function __construct(
        private Transaction $transaction,
        private Dispatcher $events,
        private AuditRecorder $audit,
    ) {}

    /**
     * @param array<string, mixed> $data بيانات المجموعة بعد تحقّق FormRequest
     */
    public function execute(array $data, ?string $actorId = null, ?string $reason = null): Group
    {
        $code = (string) $data['code'];
        $organizationId = (string) $data['organization_id'];
        // تاريخ البدء مؤجَّل في المسودة؛ يُستوفى قبل التفعيل لا عند الإنشاء.
        $startsOn = isset($data['starts_on']) ? (string) $data['starts_on'] : null;
        $endsOn = isset($data['ends_on']) ? (string) $data['ends_on'] : null;

        $this->assertCodeAvailable($code);
        $this->assertEndsAfterStarts($startsOn, $endsOn);

        $group = $this->transaction->run(function () use ($data, $actorId, $reason): Group {
            $group = new Group;
            $group->fill(Arr::except($data, ['reason']));
            $group->status = GroupStatus::Planning;
            $group->save();

            if ($actorId !== null && $reason !== null && trim($reason) !== '') {
                $this->audit->record(
                    organizationId: (string) $group->organization_id,
                    actorId: $actorId,
                    actorType: 'user',
                    action: 'groups.group_created',
                    auditableType: 'groups',
                    auditableId: (string) $group->getKey(),
                    oldValues: null,
                    newValues: [
                        'code' => $group->code,
                        'capacity' => $group->capacity,
                        'status' => $group->status->value,
                    ],
                    reason: trim($reason),
                );
            }

            return $group;
        });

        $this->events->dispatch(new GroupCreated(
            groupId: (string) $group->getKey(),
            organizationId: $organizationId,
            code: (string) $group->code,
            status: $group->status->value,
            capacity: (int) $group->capacity,
        ));

        return $group;
    }

    private function assertCodeAvailable(string $code): void
    {
        $exists = Group::query()
            ->withTrashed()
            ->where('code', $code)
            ->exists();

        if ($exists) {
            throw BusinessRuleViolation::make(
                'groups.code_taken',
                'groups::errors.code_taken',
                ['code' => $code],
            );
        }
    }

    private function assertEndsAfterStarts(?string $startsOn, ?string $endsOn): void
    {
        if ($startsOn === null || $endsOn === null) {
            return;
        }

        if ($endsOn < $startsOn) {
            throw BusinessRuleViolation::make(
                'groups.ends_before_starts',
                'groups::errors.ends_before_starts',
                ['starts_on' => $startsOn, 'ends_on' => $endsOn],
            );
        }
    }
}
