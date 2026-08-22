<?php

declare(strict_types=1);

namespace Modules\Groups\Application\Actions;

use Illuminate\Contracts\Events\Dispatcher;
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
    ) {}

    /**
     * @param array<string, mixed> $data بيانات المجموعة بعد تحقّق FormRequest
     */
    public function execute(array $data): Group
    {
        $code = (string) $data['code'];
        $organizationId = (string) $data['organization_id'];
        $startsOn = (string) $data['starts_on'];
        $endsOn = isset($data['ends_on']) && $data['ends_on'] !== null ? (string) $data['ends_on'] : null;

        $this->assertCodeAvailable($code);
        $this->assertEndsAfterStarts($startsOn, $endsOn);

        $group = $this->transaction->run(function () use ($data): Group {
            $group = new Group;
            $group->fill($data);
            $group->status = GroupStatus::Planning;
            $group->save();

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

    private function assertEndsAfterStarts(string $startsOn, ?string $endsOn): void
    {
        if ($endsOn === null) {
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
