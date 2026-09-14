<?php

declare(strict_types=1);

namespace Modules\Payroll\Application\Listeners;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Modules\Academics\Domain\Contracts\ProgramRulesQueries;
use Modules\Audit\Domain\Contracts\AuditRecorder;
use Modules\Payroll\Application\Actions\RecordPayrollEntryAction;
use Modules\Payroll\Application\Actions\SettleMakeupSessionAction;
use Modules\Payroll\Application\Services\PayrollPeriodResolver;
use Modules\Sessions\Domain\Contracts\SessionFactsQueries;
use Modules\Sessions\Domain\Events\SessionPostponed;
use Modules\Sessions\Domain\Events\TeacherApologyDecided;
use Modules\Sessions\Domain\ValueObjects\SessionPayrollFacts;
use Modules\Staff\Domain\Contracts\TeacherRateResolver;
use Modules\Staff\Domain\Enums\RateScope;
use Shared\Domain\DomainEvent;
use Shared\Support\BusinessRuleViolation;
use Shared\ValueObjects\Money;
use Shared\ValueObjects\TimeRange;

/**
 * تحويل نتيجة الحصة إلى قيدة في دفتر المستحقات.
 *
 * هذا هو الرابط الذي كان مفقودًا بين دورة حياة الحصة والدفتر: كل البنية
 * كانت قائمة (`RecordPayrollEntryAction`، مصفوفة النتائج، محلّل السعر) ولم
 * يكن أحد يستدعيها، فكانت الحصص تُقفل بلا أي أثر مالي.
 *
 * القرارات كلها من `config/payroll.php`:
 *   حالة الحصة → `status_outcomes` → مفتاح في `outcomes` → أثر المعلم
 *   (full · deduct · deferred · none) → نوع القيدة من `entry_types`.
 * لا شرط `if` على اسم حالة أو برنامج داخل هذا الصنف.
 *
 * القراءة عبر عقود معلنة فقط: حقائق الحصة من Sessions، والبرنامج من
 * Academics، والسعر والعقد من Staff. لا يقرأ Payroll جدولًا لا يملكه.
 */
final readonly class RecordSessionPayrollEntry
{
    public function __construct(
        private SessionFactsQueries $sessions,
        private ProgramRulesQueries $programs,
        private TeacherRateResolver $rates,
        private PayrollPeriodResolver $periods,
        private RecordPayrollEntryAction $record,
        private SettleMakeupSessionAction $settleMakeup,
        private AuditRecorder $audit,
    ) {}

    public function handle(DomainEvent $event): void
    {
        $sessionId = $this->sessionIdOf($event);

        if ($sessionId === null) {
            return;
        }

        $facts = $this->sessions->payrollFactsFor($sessionId);

        if ($facts === null) {
            return;
        }

        $isApprovedApology = $event instanceof TeacherApologyDecided
            && $event->substituteRequired
            && $event->decision === 'approved';

        if (!$isApprovedApology
            && $facts->hasApprovedTeacherApology
            && !$facts->hasSubstitute()) {
            return;
        }

        $outcomeKey = $isApprovedApology
            ? config('payroll.teacher_apology.approved_outcome')
            : $this->outcomeKeyFor($facts);

        if (!is_string($outcomeKey)) {
            return;
        }

        $configuredEffect = (string) config("payroll.outcomes.{$outcomeKey}.teacher", 'none');

        if ($configuredEffect === 'none') {
            return;
        }

        $staffProfileId = $isApprovedApology
            ? $event->staffProfileId
            : $facts->staffProfileId;

        /*
         * الحصة المؤجَّلة وحصتها التعويضية عمل واحد، فأجره واحد. التسوية تسبق
         * أي احتساب: إن حرّرت قيدة الأصلية — أو وجدتها محرَّرة من قبل — فلا
         * قيدة ثانية إطلاقًا. `FALLBACK_REQUIRED` وحدها تكمل إلى الاحتساب،
         * وتُسعَّر بالأصلية لا بالتعويضية.
         */
        $makeupFallback = false;

        if (!$isApprovedApology && $facts->isMakeup() && $this->settlesMakeupPair($outcomeKey)) {
            $settlement = $this->settleMakeup->execute(
                organizationId: $facts->organizationId,
                makeupSessionId: $facts->sessionId,
                staffProfileId: $staffProfileId,
                actorId: $event->actorId,
                reason: (string) __('payroll::actions.settle_makeup.released'),
            );

            if ($settlement === SettleMakeupSessionAction::MANUAL_REQUIRED) {
                Log::warning('payroll.makeup.manual_settlement_required', [
                    'session_id' => $facts->sessionId,
                    'staff_profile_id' => $staffProfileId,
                ]);

                $this->audit->record(
                    organizationId: $facts->organizationId,
                    actorId: $event->actorId,
                    actorType: 'system',
                    action: 'payroll.makeup.manual_settlement_required',
                    auditableType: 'sessions',
                    auditableId: $facts->sessionId,
                    oldValues: null,
                    newValues: [
                        'original_session_id' => $facts->makeupForSessionId,
                        'performed_by' => $staffProfileId,
                    ],
                    reason: (string) __('payroll::actions.settle_makeup.manual'),
                );

                return;
            }

            if ($settlement !== SettleMakeupSessionAction::FALLBACK_REQUIRED) {
                return;
            }

            $makeupFallback = true;
        }

        /*
         * سعر التعويضية هو سعر الأصلية: نوعها ومدتها وتاريخها ومقررها. نوع
         * `makeup` نفسه لا يطابق أي preset في كتالوج المؤسسة، فتسعيره بذاته
         * كان ينتهي دائمًا إلى `rate_unresolved`.
         */
        $rateFacts = $facts;

        if ($facts->isMakeup() && $facts->makeupForSessionId !== null) {
            $rateFacts = $this->sessions->payrollFactsFor($facts->makeupForSessionId) ?? $facts;
        }

        $rate = $this->resolveRate($rateFacts, $staffProfileId, $configuredEffect === 'deduct');

        if ($rate === null) {
            /*
             * معلم بلا عقد ساري أو بلا سعر مطبَّق لا تُخترع له قيدة بصفر:
             * ذلك يخفي نقص البيانات خلف رقم يبدو صحيحًا. يُسجَّل تحذير
             * ليعالجه الإشراف، وتبقى الحصة بلا أثر مالي حتى يُستكمل العقد.
             */
            Log::warning('payroll.entry.rate_unresolved', [
                'session_id' => $facts->sessionId,
                'staff_profile_id' => $staffProfileId,
                'outcome' => $outcomeKey,
            ]);

            /*
             * التحذير في اللوج وحده يجعل النقص غير مرئي لمن يدير المدرسة:
             * الحصة تُقفل، ولا يظهر للمعلم مستحق، ولا يعرف أحد السبب. القيد
             * في سجل التدقيق يجعلها واقعة قابلة للعرض والمراجعة.
             */
            $this->audit->record(
                organizationId: $facts->organizationId,
                actorId: $event->actorId,
                actorType: 'system',
                action: 'payroll.entry.rate_unresolved',
                auditableType: 'sessions',
                auditableId: $facts->sessionId,
                oldValues: null,
                newValues: [
                    'staff_profile_id' => $staffProfileId,
                    'outcome' => $outcomeKey,
                    'session_type' => $rateFacts->sessionType,
                    'duration_minutes' => (int) round(
                        $rateFacts->scheduledStart->diffInMinutes($rateFacts->scheduledEnd),
                    ),
                ],
                reason: (string) __('payroll::actions.rate_unresolved.reason'),
            );

            return;
        }

        $teacherEffect = config(
            "payroll.contract_basis_effects.{$rate['contract_basis']}.{$configuredEffect}",
            $configuredEffect,
        );

        if (!is_string($teacherEffect) || $teacherEffect === 'none') {
            return;
        }

        $entryType = config("payroll.entry_types.{$teacherEffect}");

        if (!is_string($entryType)) {
            return;
        }

        $amount = $teacherEffect === 'deduct'
            ? $rate['money']->negated()
            : $rate['money'];

        /*
         * القيدة المؤجَّلة تُعلَّق على **الحصة التعويضية** لا على الأصلية:
         * `deferred_until_session_id` تعني «مؤجَّلة حتى تُقام هذه الحصة»، وهو
         * ما يبحث به مسار التحرير. كان الكود يخزّن معرّف الأصلية نفسها، فلا
         * يطابقه أي بحث تحرير أبدًا وتبقى القيدة مؤجَّلة إلى الأبد.
         *
         * معرّف التعويضية لا يحمله `SessionPayrollFacts` للأصلية، بل يحمله
         * حدث التأجيل وحده. فإن غاب الحدث لا تُنشأ قيدة معلّقة بلا مفتاح
         * تحرير: يُسجَّل تحذير ليعالجه الإشراف.
         */
        $deferredUntilSessionId = null;

        if ($teacherEffect === 'deferred') {
            if (!$event instanceof SessionPostponed) {
                Log::warning('payroll.entry.deferral_target_unknown', [
                    'session_id' => $facts->sessionId,
                    'event' => $event::class,
                ]);

                return;
            }

            $deferredUntilSessionId = $event->makeupSessionId;
        }

        $period = $this->periods->forDate($facts->organizationId, $facts->scheduledStart);

        try {
            $this->record->execute(
                organizationId: $facts->organizationId,
                payrollPeriodId: (string) $period->getKey(),
                staffProfileId: $staffProfileId,
                teacherContractId: $rate['contract_id'],
                entryType: $entryType,
                outcomeKey: $outcomeKey,
                amount: $amount,
                sessionTime: TimeRange::of($facts->scheduledStart, $facts->scheduledEnd),
                sessionId: $facts->sessionId,
                deferredUntilSessionId: $deferredUntilSessionId,
                resolvedVia: $rate['scope']->value,
                description: [
                    'makeup_fallback' => $makeupFallback ?: null,
                    'priced_from_session_id' => $makeupFallback ? $facts->makeupForSessionId : null,
                    'session_type' => $facts->sessionType,
                    'course_id' => $facts->courseId,
                    'group_id' => $facts->groupId,
                    'rate_id' => $rate['rate_id'],
                    'duration_minutes' => (int) round($facts->scheduledStart->diffInMinutes($facts->scheduledEnd)),
                    /*
                     * التعويضية تُسعَّر بالأصلية، فنوعها ومدتها لا يفسّران
                     * مبلغها. تُذكر خصائص مصدر السعر كي تبقى القيدة مفهومة
                     * بذاتها لمن يراجع الدفتر لاحقًا.
                     */
                    'priced_as_session_type' => $rateFacts->sessionType,
                    'priced_as_duration_minutes' => (int) round(
                        $rateFacts->scheduledStart->diffInMinutes($rateFacts->scheduledEnd),
                    ),
                    'contract_basis' => $rate['contract_basis'],
                    'substituted' => $facts->hasSubstitute(),
                ],
                actorId: $event->actorId,
            );

            /*
             * قيدة fallback تعني تأجيلًا سبق هذا الإصلاح فلم تُنشأ له قيدة
             * مؤجَّلة. تُوسم في `description` وتُسجَّل هنا صراحةً كي يميّزها من
             * يراجع الدفتر عن مسار التحرير الطبيعي.
             */
            if ($makeupFallback) {
                $this->audit->record(
                    organizationId: $facts->organizationId,
                    actorId: $event->actorId,
                    actorType: 'system',
                    action: 'payroll.entry.makeup_fallback',
                    auditableType: 'payroll_entries',
                    auditableId: null,
                    oldValues: null,
                    newValues: [
                        'makeup_session_id' => $facts->sessionId,
                        'original_session_id' => $facts->makeupForSessionId,
                        'amount' => $amount->minorUnits,
                        'currency' => $amount->currency,
                        'resolved_via' => $rate['scope']->value,
                    ],
                    reason: (string) __('payroll::actions.settle_makeup.fallback'),
                );
            }
        } catch (BusinessRuleViolation $violation) {
            /*
             * تكرار القيدة أو فترة مقفلة ليسا خطأ برمجيًا: الأول يعني أن
             * الحدث وصل مرتين والقيد الفريد حماه، والثاني قرار إداري.
             * إسقاط الاستثناء هنا كان سيُفشل إقفال الحصة نفسه.
             */
            Log::warning('payroll.entry.skipped', [
                'session_id' => $facts->sessionId,
                'rule' => $violation->rule,
            ]);
        }
    }

    /** هل تعني هذه النتيجة أن المعلم أدّى عمله، فتُسوَّى بها قيدة الأصلية؟ */
    private function settlesMakeupPair(string $outcomeKey): bool
    {
        return in_array($outcomeKey, (array) config('payroll.makeup.releasing_outcomes'), true);
    }

    /**
     * مفتاح النتيجة المطبَّق على هذه الحصة.
     *
     * حصة التلافي المكتملة لها مفتاحها الخاص لأنها تُحرّر مستحقًا مؤجَّلًا،
     * وإلغاء الطالب بعد انقضاء المهلة يُعامل معاملة التغيّب وفق قرار العميل.
     */
    private function outcomeKeyFor(SessionPayrollFacts $facts): ?string
    {
        $mapped = config("payroll.status_outcomes.{$facts->status}");

        if (!is_string($mapped)) {
            return null;
        }

        if ($mapped === 'completed' && $facts->isMakeup()) {
            $makeup = config('payroll.makeup_outcome');

            return is_string($makeup) ? $makeup : $mapped;
        }

        $studentApology = config('payroll.student_apology');
        if (
            $facts->hasStudentApology
            && is_array($studentApology)
            && ($studentApology['applies_to_status'] ?? null) === $facts->status
        ) {
            $individualOutcome = $studentApology['individual_outcome'] ?? null;

            return is_string($individualOutcome) ? $individualOutcome : $mapped;
        }

        return $this->applyLateCancellation($facts, $mapped);
    }

    private function applyLateCancellation(SessionPayrollFacts $facts, string $outcomeKey): string
    {
        $rule = config('payroll.late_cancellation');

        if (!is_array($rule) || ($rule['enabled'] ?? false) !== true) {
            return $outcomeKey;
        }

        if (($rule['applies_to_status'] ?? null) !== $facts->status) {
            return $outcomeKey;
        }

        $deadlineKey = $rule['deadline_config_key'] ?? null;

        if (!is_string($deadlineKey)) {
            return $outcomeKey;
        }

        $deadlineMinutes = (int) config($deadlineKey, 0);
        $noticeMinutes = CarbonImmutable::now('UTC')->diffInMinutes($facts->scheduledStart, false);

        if ($noticeMinutes >= $deadlineMinutes) {
            return $outcomeKey;
        }

        $lateOutcome = $rule['outcome'] ?? null;

        return is_string($lateOutcome) ? $lateOutcome : $outcomeKey;
    }

    /**
     * @return array{money: Money, scope: RateScope, rate_id: string, contract_id: string, contract_basis: string}|null
     */
    private function resolveRate(
        SessionPayrollFacts $facts,
        string $staffProfileId,
        bool $forDeduction,
    ): ?array {
        $programIds = $this->programs->programIdsOfCourse($facts->courseId);
        $programId = $programIds === [] ? null : (string) reset($programIds);

        $arguments = [
            'staffProfileId' => $staffProfileId,
            'sessionDate' => $facts->scheduledStart,
            'programId' => $programId,
            'courseId' => $facts->courseId,
            'sessionType' => $facts->sessionType,
            'durationMinutes' => (int) round($facts->scheduledStart->diffInMinutes($facts->scheduledEnd)),
        ];

        return $forDeduction
            ? $this->rates->resolveDeduction(...$arguments)
            : $this->rates->resolve(...$arguments);
    }

    private function sessionIdOf(DomainEvent $event): ?string
    {
        $sessionId = $event->payload()['session_id'] ?? null;

        return is_string($sessionId) && $sessionId !== '' ? $sessionId : null;
    }
}
