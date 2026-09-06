<?php

declare(strict_types=1);

namespace Modules\Payroll\Application\Listeners;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Modules\Academics\Domain\Contracts\ProgramRulesQueries;
use Modules\Payroll\Application\Actions\RecordPayrollEntryAction;
use Modules\Payroll\Application\Services\PayrollPeriodResolver;
use Modules\Sessions\Domain\Contracts\SessionFactsQueries;
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
        $rate = $this->resolveRate($facts, $staffProfileId, $configuredEffect === 'deduct');

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
                deferredUntilSessionId: $teacherEffect === 'deferred' ? $facts->sessionId : null,
                resolvedVia: $rate['scope']->value,
                description: [
                    'session_type' => $facts->sessionType,
                    'course_id' => $facts->courseId,
                    'group_id' => $facts->groupId,
                    'rate_id' => $rate['rate_id'],
                    'contract_basis' => $rate['contract_basis'],
                    'substituted' => $facts->hasSubstitute(),
                ],
                actorId: $event->actorId,
            );
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
