<?php

declare(strict_types=1);

namespace Modules\Academics\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Modules\Academics\Domain\Events\ProgramClosed;
use Modules\Academics\Domain\Models\Program;
use Modules\Audit\Domain\Contracts\AuditRecorder;
use Shared\Archive\ClosureSnapshot;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;

/**
 * إقفال برنامج — إخراجه من الواجهة مع تجميد حصيلته.
 *
 * الإقفال إخراجٌ من الواجهة مع تجميد الحصيلة، وليس حذفًا: لا يُمسّ `deleted_at`
 * ولا سطر واحد من البيانات تحته. الاسترجاع متاح دائمًا بإعادة الفتح.
 *
 * الحصيلة تصل جاهزة من طبقة القراءة ولا يحسبها الأكشن بنفسه، كي لا يعتمد
 * موديول الطبقة 2 على موديولات التشغيل فوقه. ومع ذلك يفحص الأكشن الموانع
 * الواصلة معها: الحارس في المجال، لا في الواجهة وحدها.
 *
 * الفاعل إلزامي — إقفال بلا منسوب إليه يفقد السجلَّ معناه.
 */
final readonly class CloseProgramAction
{
    public function __construct(
        private Transaction $transaction,
        private Dispatcher $events,
        private AuditRecorder $audit,
    ) {}

    public function execute(
        Program $subject,
        string $reason,
        ClosureSnapshot $snapshot,
        string $actorId,
    ): Program {
        $reason = trim($reason);

        if ($reason === '') {
            throw BusinessRuleViolation::make('academics.reason_required', 'academics::errors.reason_required');
        }

        if ($subject->closed_at !== null) {
            throw BusinessRuleViolation::make(
                'academics.program_already_closed',
                'academics::errors.program_already_closed',
                ['code' => (string) $subject->code],
            );
        }

        if ($snapshot->isBlocked()) {
            throw BusinessRuleViolation::make(
                'academics.program_closure_blocked',
                'academics::errors.program_closure_blocked',
                ['code' => (string) $subject->code, 'blockers' => $this->describeBlockers($snapshot)],
            );
        }

        $closedAt = CarbonImmutable::now('UTC');

        $subject = $this->transaction->run(function () use ($subject, $reason, $snapshot, $closedAt, $actorId): Program {
            // قفل الصف وإعادة الفحص داخل المعاملة: طلبان متزامنان يريان كلاهما
            // `closed_at = null` قبلها، فيكتبان قيدتَي إقفال لعملية واحدة وتكذب
            // ثانيتهما في `old_values`.
            $locked = $subject->newQuery()->whereKey($subject->getKey())->lockForUpdate()->first();

            if ($locked === null || $locked->closed_at !== null) {
                throw BusinessRuleViolation::make(
                    'academics.program_already_closed',
                    'academics::errors.program_already_closed',
                    ['code' => (string) $subject->code],
                );
            }

            $subject->closed_at = $closedAt;
            $subject->closed_by = $actorId;
            $subject->closure_reason = $reason;
            $subject->closure_summary = $snapshot->summary;
            $subject->save();

            $this->audit->record(
                organizationId: (string) $subject->organization_id,
                actorId: $actorId,
                actorType: 'user',
                action: 'academics.program_closed',
                auditableType: 'programs',
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

        $this->events->dispatch(new ProgramClosed(
            programId: (string) $subject->getKey(),
            organizationId: (string) $subject->organization_id,
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
