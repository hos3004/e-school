<?php

declare(strict_types=1);

namespace Modules\Academics\Application\Actions;

use Illuminate\Contracts\Events\Dispatcher;
use Modules\Academics\Domain\Events\LevelReopened;
use Modules\Academics\Domain\Models\Level;
use Modules\Audit\Domain\Contracts\AuditRecorder;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;

/**
 * إعادة فتح مستوى مُقفل بسبب موثّق.
 *
 * إعادة الفتح تمحو علامة الإقفال وحصيلته المجمَّدة من الصف، لأن الحصيلة تصف
 * لحظة إقفال انتهت. لكنها لا تضيع: قيدة التدقيق تحفظ الحصيلة كاملة في
 * `old_values`، فيبقى «ماذا كان وقت الإقفال» مقروءًا بعد إعادة الفتح.
 */
final readonly class ReopenLevelAction
{
    public function __construct(
        private Transaction $transaction,
        private Dispatcher $events,
        private AuditRecorder $audit,
    ) {}

    public function execute(Level $subject, string $reason, string $actorId): Level
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw BusinessRuleViolation::make('academics.reason_required', 'academics::errors.reason_required');
        }

        $organizationId = $subject->program?->organization_id;

        if (!is_string($organizationId) || $organizationId === '') {
            throw BusinessRuleViolation::make(
                'academics.organization_required',
                'academics::errors.organization_required',
            );
        }

        if ($subject->closed_at === null) {
            throw BusinessRuleViolation::make(
                'academics.level_not_closed',
                'academics::errors.level_not_closed',
                ['code' => (string) $subject->code],
            );
        }

        $subject = $this->transaction->run(function () use ($subject, $reason, $actorId, $organizationId): Level {
            $locked = $subject->newQuery()->whereKey($subject->getKey())->lockForUpdate()->first();

            if ($locked === null || $locked->closed_at === null) {
                throw BusinessRuleViolation::make(
                    'academics.level_not_closed',
                    'academics::errors.level_not_closed',
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
                organizationId: $organizationId,
                actorId: $actorId,
                actorType: 'user',
                action: 'academics.level_reopened',
                auditableType: 'levels',
                auditableId: (string) $subject->getKey(),
                oldValues: $previous,
                newValues: ['closed_at' => null],
                reason: $reason,
            );

            return $subject;
        });

        $this->events->dispatch(new LevelReopened(
            levelId: (string) $subject->getKey(),
            organizationId: $organizationId,
            reason: $reason,
            actorId: $actorId,
        ));

        return $subject;
    }
}
