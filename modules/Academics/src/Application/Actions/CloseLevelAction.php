<?php

declare(strict_types=1);

namespace Modules\Academics\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Modules\Academics\Domain\Events\LevelClosed;
use Modules\Academics\Domain\Models\Level;
use Modules\Audit\Domain\Contracts\AuditRecorder;
use Shared\Archive\ClosureSnapshot;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;

/**
 * إقفال مستوى — إخراجه من الواجهة مع تجميد حصيلته.
 *
 * الإقفال إخراجٌ من الواجهة وليس حذفًا: لا يُمسّ سطر واحد مما تحته، وإعادة
 * الفتح متاحة دائمًا.
 *
 * المستوى بلا `organization_id` خاص به — يرثها من برنامجه. لذلك تُقرأ المؤسسة
 * من البرنامج عند كتابة قيدة التدقيق، ويُرفض الإقفال إن تعذّر تحديدها: قيدة
 * تدقيق بلا مؤسسة تسقط من كل تقرير مؤسسة لاحقًا.
 */
final readonly class CloseLevelAction
{
    public function __construct(
        private Transaction $transaction,
        private Dispatcher $events,
        private AuditRecorder $audit,
    ) {}

    public function execute(
        Level $subject,
        string $reason,
        ClosureSnapshot $snapshot,
        string $actorId,
    ): Level {
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

        if ($subject->closed_at !== null) {
            throw BusinessRuleViolation::make(
                'academics.level_already_closed',
                'academics::errors.level_already_closed',
                ['code' => (string) $subject->code],
            );
        }

        if ($snapshot->isBlocked()) {
            throw BusinessRuleViolation::make(
                'academics.level_closure_blocked',
                'academics::errors.level_closure_blocked',
                ['code' => (string) $subject->code, 'blockers' => $this->describeBlockers($snapshot)],
            );
        }

        $closedAt = CarbonImmutable::now('UTC');

        $subject = $this->transaction->run(function () use ($subject, $reason, $snapshot, $closedAt, $actorId, $organizationId): Level {
            // قفل الصف وإعادة الفحص داخل المعاملة: طلبان متزامنان يريان كلاهما
            // `closed_at = null` قبلها، فيكتبان قيدتَي إقفال لعملية واحدة.
            $locked = $subject->newQuery()->whereKey($subject->getKey())->lockForUpdate()->first();

            if ($locked === null || $locked->closed_at !== null) {
                throw BusinessRuleViolation::make(
                    'academics.level_already_closed',
                    'academics::errors.level_already_closed',
                    ['code' => (string) $subject->code],
                );
            }

            $subject->closed_at = $closedAt;
            $subject->closed_by = $actorId;
            $subject->closure_reason = $reason;
            $subject->closure_summary = $snapshot->summary;
            $subject->save();

            $this->audit->record(
                organizationId: $organizationId,
                actorId: $actorId,
                actorType: 'user',
                action: 'academics.level_closed',
                auditableType: 'levels',
                auditableId: (string) $subject->getKey(),
                oldValues: ['closed_at' => null],
                newValues: [
                    'closed_at' => $closedAt->toIso8601String(),
                    'closure_summary' => $snapshot->summary,
                ],
                reason: $reason,
            );

            return $subject;
        });

        $this->events->dispatch(new LevelClosed(
            levelId: (string) $subject->getKey(),
            organizationId: $organizationId,
            reason: $reason,
            actorId: $actorId,
        ));

        return $subject;
    }

    /**
     * يترجم الموانع إلى نص يفهمه المستخدم.
     *
     * الرسالة تذكر ما يمنع الإقفال وعدده، لا «غير مسموح» وحدها: المستخدم يحتاج
     * أن يعرف ما الذي يعالجه ليُكمل، لا أن يُصدّ بلا طريق.
     */
    private function describeBlockers(ClosureSnapshot $snapshot): string
    {
        $described = [];

        foreach ($snapshot->blockers as $key => $count) {
            $described[] = __('academics::errors.closure_blocker_'.$key, ['count' => $count]);
        }

        return implode('، ', $described);
    }
}
