<?php

declare(strict_types=1);

namespace Modules\Groups\Application\Actions;

use Illuminate\Contracts\Events\Dispatcher;
use Modules\Audit\Domain\Contracts\AuditRecorder;
use Modules\Groups\Domain\Events\GroupReopened;
use Modules\Groups\Domain\Models\Group;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;

/**
 * إعادة فتح مجموعة مُقفلة بسبب موثّق.
 *
 * إعادة الفتح تمحو علامة الإقفال وحصيلته المجمَّدة من الصف، لأن الحصيلة تصف
 * لحظة إقفال انتهت. لكنها لا تضيع: قيدة التدقيق تحفظ الحصيلة كاملة في
 * `old_values`، فيبقى «ماذا كان وقت الإقفال» مقروءًا بعد إعادة الفتح.
 */
final readonly class ReopenGroupAction
{
    public function __construct(
        private Transaction $transaction,
        private Dispatcher $events,
        private AuditRecorder $audit,
    ) {}

    public function execute(Group $subject, string $reason, string $actorId): Group
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw BusinessRuleViolation::make('groups.reason_required', 'groups::errors.closure_reason_required');
        }

        if ($subject->closed_at === null) {
            throw BusinessRuleViolation::make(
                'groups.group_not_closed',
                'groups::errors.group_not_closed',
                ['code' => (string) $subject->code],
            );
        }

        $subject = $this->transaction->run(function () use ($subject, $reason, $actorId): Group {
            $locked = $subject->newQuery()->whereKey($subject->getKey())->lockForUpdate()->first();

            if ($locked === null || $locked->closed_at === null) {
                throw BusinessRuleViolation::make(
                    'groups.group_not_closed',
                    'groups::errors.group_not_closed',
                    ['code' => (string) $subject->code],
                );
            }

            $previous = [
                'closed_at' => $subject->closed_at?->toIso8601String(),
                'closure_reason' => $subject->closure_reason,
                'closure_summary' => $subject->closure_summary,
            ];

            $subject->closed_at = null;
            $subject->closed_by = null;
            $subject->closure_reason = null;
            $subject->closure_summary = null;
            $subject->save();

            $this->audit->record(
                organizationId: (string) $subject->organization_id,
                actorId: $actorId,
                actorType: 'user',
                action: 'groups.group_reopened',
                auditableType: 'groups',
                auditableId: (string) $subject->getKey(),
                oldValues: $previous,
                newValues: ['closed_at' => null],
                reason: $reason,
            );

            return $subject;
        });

        $this->events->dispatch(new GroupReopened(
            groupId: (string) $subject->getKey(),
            organizationId: (string) $subject->organization_id,
            reason: $reason,
            actorId: $actorId,
        ));

        return $subject;
    }
}
