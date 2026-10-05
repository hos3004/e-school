<?php

declare(strict_types=1);

namespace App\Services\Console;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;
use Modules\Audit\Domain\Contracts\AuditRecorder;
use Modules\Payroll\Domain\Contracts\TeacherDuesQueries;
use Modules\Sessions\Application\Actions\CancelSessionAction;
use Modules\Sessions\Application\Actions\CompleteSessionAction;
use Modules\Sessions\Application\Actions\ExcuseAbsenceAction;
use Modules\Sessions\Application\Actions\MarkNoShowAction;
use Modules\Sessions\Application\Actions\RecordOffPlatformSessionAction;
use Modules\Sessions\Domain\Enums\SessionStatus;
use Modules\Sessions\Domain\Models\Session;
use Modules\Staff\Domain\Contracts\TeacherRateResolver;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;

/**
 * الطريق الوحيد لإقفال حصة من لوحة التحكم.
 *
 * كان هذا المنطق يعيش داخل SessionReviewController وحده. حين صار الاعتماد
 * مطلوبًا من ملف حسابات المعلم أيضًا، كان أمامنا إما نسخه — فيصير للحصة
 * طريقان للإقفال يتباعدان مع أول تعديل — أو إخراجه إلى مكان واحد يستدعيه
 * الاثنان. هذا هو المكان الواحد.
 *
 * القرارات الأربعة هي نفسها التي كانت في شاشة الاعتماد: كل واحد يمر بـPolicy
 * الخاصة به ويستدعي Action موديول Sessions — لا انتقال حالة يُكتب هنا.
 */
final readonly class SessionDecisionService
{
    public const DECISIONS = ['complete', 'no_show', 'excused', 'cancelled_by_school'];

    public function __construct(
        private RecordOffPlatformSessionAction $recordOffPlatform,
        private CompleteSessionAction $complete,
        private MarkNoShowAction $noShow,
        private ExcuseAbsenceAction $excuse,
        private CancelSessionAction $cancel,
        private AuditRecorder $audit,
        private Transaction $transaction,
        private TeacherDuesQueries $periods,
        private TeacherRateResolver $rates,
    ) {}

    /**
     * @param int|null $rateOverrideMinorUnits أجر يدوي لهذه الحصة وحدها، يُقبل مع
     *                                         `complete` فقط وقبل إنشاء أي قيدة.
     */
    public function decide(
        Session $session,
        string $decision,
        string $actorId,
        string $reason,
        ?int $rateOverrideMinorUnits = null,
    ): void {
        if (!in_array($decision, self::DECISIONS, true)) {
            throw BusinessRuleViolation::make(
                'console.session_decision.unknown',
                'console_dues.unknown_decision',
            );
        }

        if ($rateOverrideMinorUnits !== null && $decision !== 'complete') {
            throw BusinessRuleViolation::make(
                'console.session_decision.override_not_applicable',
                'console_dues.override_not_applicable',
            );
        }

        $this->assertPeriodAcceptsEntries($session);

        if ($rateOverrideMinorUnits !== null) {
            $this->assertOverrideIsUsable($session, $rateOverrideMinorUnits);
        }

        match ($decision) {
            'complete' => $this->completeSession($session, $actorId, $reason, $rateOverrideMinorUnits),
            'no_show' => $this->markNoShow($session, $actorId, $reason),
            'excused' => $this->excuseSession($session, $actorId, $reason),
            'cancelled_by_school' => $this->cancelSession($session, $actorId, $reason),
        };
    }

    /**
     * القيدة تُنسب إلى شهر الحصة. لو كان مقفلًا، يرفض الدفتر القيدة بينما
     * الحصة تُقفل بنجاح والمستمع يبتلع الرفض في السجل — فتصير حصة نهائية بلا
     * مستحق لا يعرف بها أحد. الرفض هنا يسبق الإقفال ويشرح البديل.
     */
    private function assertPeriodAcceptsEntries(Session $session): void
    {
        $accepts = $this->periods->acceptsEntriesOn(
            (string) $session->organization_id,
            CarbonImmutable::parse((string) $session->scheduled_start),
        );

        if (!$accepts) {
            throw BusinessRuleViolation::make(
                'console.session_decision.period_closed',
                'console_dues.session_period_closed',
            );
        }
    }

    /**
     * كل أسباب رفض الأجر اليدوي تُفحص **قبل** أي انتقال حالة، فلا تبقى الحصة
     * نصف مقرَّرة حين يُرفض المبلغ.
     */
    private function assertOverrideIsUsable(Session $session, int $override): void
    {
        if ($override <= 0 || $override > (int) config('payroll.session_manual_amount_max_minor_units')) {
            throw BusinessRuleViolation::make(
                'console.session_decision.override_out_of_range',
                'console_dues.override_out_of_range',
                ['max' => number_format((int) config('payroll.session_manual_amount_max_minor_units') / 100, 2)],
            );
        }

        if ((bool) $session->payroll_exempt === true) {
            throw BusinessRuleViolation::make(
                'console.session_decision.override_on_exempt',
                'console_dues.override_on_exempt',
            );
        }

        /*
         * الحصة التعويضية لا تُسعَّر بنفسها: قيدتها هي قيدة الأصلية المؤجَّلة
         * تُفرَج عند تنفيذ التعويض، فمستمع المستحقات لا يصل إلى محلّل السعر
         * أصلًا. قبول مبلغ هنا كان سيكتبه في الحصة ولا يقرأه أحد، فيظن المدير
         * أنه سعّرها.
         */
        if ($session->makeup_for_session_id !== null) {
            throw BusinessRuleViolation::make(
                'console.session_decision.override_on_makeup',
                'console_dues.override_on_makeup',
            );
        }

        /*
         * الأجر اليدوي يحل محل السعر لا محل العقد: القيدة تُنسب إلى العقد
         * الساري وقت الحصة، وبدونه يخرج المستمع بلا قيدة ويظهر صفر بلا سبب.
         */
        if ($this->rates->activeContract(
            (string) $session->staff_profile_id,
            CarbonImmutable::parse((string) $session->scheduled_start),
        ) === null) {
            throw BusinessRuleViolation::make(
                'console.session_decision.override_without_contract',
                'console_dues.override_without_contract',
            );
        }
    }

    private function completeSession(Session $session, string $actorId, string $reason, ?int $override): void
    {
        Gate::authorize('complete', $session);

        $this->transaction->run(function () use ($session, $actorId, $reason, $override): void {
            /*
             * `scheduled → completed` ليس انتقالًا مشروعًا، والحصص التي لم تُفتح
             * من المنصة هي أغلب ما يُعتمد هنا. تُنقل أولًا عبر مسارها المشروع إلى
             * `awaiting_review` بموعدها المجدول كوقت تنفيذ، ثم تُعتمد بنفس إجراء
             * الاعتماد الوحيد.
             *
             * الأجر اليدوي يُكتب **بعد** نجاح هذا الانتقال وداخل نفس المعاملة:
             * كتابته قبله كانت تترك سعرًا يدويًا على حصة لم تُعتمد إن فشل
             * الانتقال، فيُدفع لاحقًا عند اعتمادها الطبيعي بسعر لم يقصده أحد.
             */
            $moved = $this->recordOffPlatform->execute($session, $actorId, $reason);

            if ($override !== null) {
                $this->applyRateOverride($moved, $actorId, $reason, $override);
            }

            $this->complete->execute($moved, $actorId, $reason);
        });
    }

    private function applyRateOverride(Session $session, string $actorId, string $reason, int $override): void
    {
        $previous = $session->payroll_rate_override_minor_units === null
            ? null
            : (int) $session->payroll_rate_override_minor_units;

        $session->forceFill(['payroll_rate_override_minor_units' => $override])->save();

        $this->audit->record(
            organizationId: (string) $session->organization_id,
            actorId: $actorId,
            actorType: 'user',
            action: 'sessions.payroll_rate_override_set',
            auditableType: 'sessions',
            auditableId: (string) $session->getKey(),
            oldValues: ['payroll_rate_override_minor_units' => $previous],
            newValues: ['payroll_rate_override_minor_units' => $override],
            reason: $reason,
        );
    }

    private function markNoShow(Session $session, string $actorId, string $reason): void
    {
        Gate::authorize('markNoShow', $session);
        $this->noShow->execute($session, $reason, $actorId);
    }

    private function excuseSession(Session $session, string $actorId, string $reason): void
    {
        Gate::authorize('excuse', $session);
        $this->excuse->execute($session, $reason, $actorId);
    }

    private function cancelSession(Session $session, string $actorId, string $reason): void
    {
        Gate::authorize('cancel', $session);
        $this->cancel->execute($session, SessionStatus::CancelledBySchool, $reason, $actorId);
    }
}
