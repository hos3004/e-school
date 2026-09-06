<?php

declare(strict_types=1);

namespace Modules\Sessions\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\DB;
use Modules\Audit\Domain\Contracts\AuditRecorder;
use Modules\Sessions\Domain\Enums\ApologyStatus;
use Modules\Sessions\Domain\Events\TeacherApologyDecided;
use Modules\Sessions\Domain\Models\Session;
use Modules\Sessions\Domain\Models\TeacherApology;
use Modules\Sessions\Domain\Services\ApologyEscalationEvaluator;
use Modules\Staff\Domain\Contracts\StaffQueries;
use Shared\Support\BusinessRuleViolation;

/**
 * انتقال اعتذار المعلم إلى قرار نهائي. مسار التقديم يستعمل الاعتماد تلقائيًا؛
 * وتبقى عملية الرفض الداخلية للتصحيح الإداري المسبب فقط.
 *
 * **القاعدة الحاكمة (docs/client-answers.md §ي):**
 * اعتماد الاعتذار **لا يُلغي الحصة ولا يغيّر حالتها**. الحصة تبقى بموعدها
 * وحالتها، ويبدأ البحث عن بديل. هذا الإجراء لا يلمس `sessions.status`
 * إطلاقًا — وأي كود مستقبلي يفعل ذلك يكسر عقد العميل.
 *
 * **ولا يمسّ حالة المعلم (§ك):** لا تعليق ولا إنهاء ولا تغيير حالة آليًا
 * مهما بلغت مرتبة الاعتذار في السُلَّم. أقصى الأثر إخطار وتصعيد للإدارة،
 * والقرار النهائي يدوي.
 *
 * سُلَّم المتابعة يُحتسب على نافذة **متحركة** (آخر N يومًا) لا على شهر ميلادي.
 */
final readonly class DecideTeacherApologyAction
{
    public function __construct(
        private Dispatcher $events,
        private ApologyEscalationEvaluator $escalation,
        private AuditRecorder $audit,
        private StaffQueries $staff,
    ) {}

    public function approve(
        string $apologyId,
        string $decidedBy,
        ?string $decisionReason = null,
        ?CarbonImmutable $now = null,
        ?string $expectedOrganizationId = null,
        ?string $expectedSessionId = null,
    ): TeacherApology {
        return $this->decide(
            $apologyId,
            ApologyStatus::Approved,
            $decidedBy,
            $decisionReason,
            $now,
            $expectedOrganizationId,
            $expectedSessionId,
        );
    }

    public function reject(
        string $apologyId,
        string $decidedBy,
        string $decisionReason,
        ?CarbonImmutable $now = null,
        ?string $expectedOrganizationId = null,
        ?string $expectedSessionId = null,
    ): TeacherApology {
        if (trim($decisionReason) === '') {
            throw BusinessRuleViolation::make(
                'sessions.apology_rejection_reason_required',
                'sessions::errors.apology_rejection_reason_required',
            );
        }

        return $this->decide(
            $apologyId,
            ApologyStatus::Rejected,
            $decidedBy,
            $decisionReason,
            $now,
            $expectedOrganizationId,
            $expectedSessionId,
        );
    }

    private function decide(
        string $apologyId,
        ApologyStatus $target,
        string $decidedBy,
        ?string $decisionReason,
        ?CarbonImmutable $now,
        ?string $expectedOrganizationId,
        ?string $expectedSessionId,
    ): TeacherApology {
        $now ??= CarbonImmutable::now('UTC');

        $apology = TeacherApology::query()->findOrFail($apologyId);

        if (($expectedOrganizationId !== null && (string) $apology->organization_id !== $expectedOrganizationId)
            || ($expectedSessionId !== null && (string) $apology->session_id !== $expectedSessionId)) {
            throw BusinessRuleViolation::make(
                'sessions.apology_context_mismatch',
                'sessions::errors.apology_context_mismatch',
            );
        }

        if (!$apology->status->canTransitionTo($target)) {
            throw BusinessRuleViolation::make(
                'sessions.apology_invalid_transition',
                'sessions::errors.apology_invalid_transition',
                ['from' => $apology->status->value, 'to' => $target->value],
            );
        }

        $session = Session::query()->findOrFail($apology->session_id);
        $statusBefore = $session->status;

        /*
         * مرتبة هذا الاعتذار في النافذة المتحركة — تُحسب وتُجمَّد لحظة الاعتماد
         * فقط. الاعتذار المرفوض لا يدخل السُلَّم أصلًا.
         */
        $verdict = $target === ApologyStatus::Approved
            ? $this->escalation->evaluate((string) $apology->staff_profile_id, $now)
            : null;

        DB::transaction(function () use ($apology, $session, $target, $decidedBy, $decisionReason, $now, $verdict): void {
            $from = $apology->status;
            $apology->status = $target;
            $apology->decided_by = $decidedBy;
            $apology->decided_at = $now;
            $apology->decision_reason = $decisionReason === null ? null : trim($decisionReason);

            if ($verdict !== null) {
                $apology->occurrence_in_window = $verdict['occurrence'];
                $apology->window_days = $verdict['window_days'];
            }

            $apology->save();

            $this->audit->record(
                organizationId: (string) $session->organization_id,
                actorId: $decidedBy,
                actorType: 'user',
                action: 'sessions.teacher_apology_'.$target->value,
                auditableType: 'teacher_apologies',
                auditableId: (string) $apology->getKey(),
                oldValues: ['status' => $from->value],
                newValues: [
                    'status' => $target->value,
                    'occurrence_in_window' => $verdict['occurrence'] ?? null,
                    'window_days' => $verdict['window_days'] ?? null,
                ],
                reason: trim((string) ($decisionReason ?? __('sessions::messages.apology_approved_reason'))),
            );
        });

        /*
         * تأكيد صريح ومقصود: حالة الحصة لم تتغيّر بأي حال.
         * هذا ليس تعليقًا تجميليًا — هو الفرق بين تنفيذ صحيح لعقد العميل
         * وتنفيذ يلغي حصص الطلاب كلما اعتذر معلم.
         */
        $session->refresh();

        if ($session->status !== $statusBefore) {
            throw BusinessRuleViolation::make(
                'sessions.apology_must_not_change_session',
                'sessions::errors.apology_must_not_change_session',
            );
        }

        $this->events->dispatch(new TeacherApologyDecided(
            sessionId: (string) $session->id,
            organizationId: (string) $session->organization_id,
            courseId: (string) $session->course_id,
            staffProfileId: (string) $apology->staff_profile_id,
            apologyId: (string) $apology->id,
            teacherUserId: $this->teacherUserId(
                (string) $session->organization_id,
                (string) $apology->staff_profile_id,
            ),
            decision: $target->value,
            substituteRequired: $target === ApologyStatus::Approved,
            occurrenceInWindow: $verdict['occurrence'] ?? null,
            windowDays: $verdict['window_days'] ?? null,
            escalationAction: $verdict['action'] ?? null,
            createsEscalation: (bool) ($verdict['creates_escalation'] ?? false),
            scheduledStart: CarbonImmutable::instance($session->scheduled_start)->toIso8601String(),
            actorId: $decidedBy,
        ));

        return $apology;
    }

    /**
     * عقد Staff يعيد هوية المستخدم دون قراءة جدول موديول آخر.
     */
    private function teacherUserId(string $organizationId, string $staffProfileId): ?string
    {
        return $this->staff->userIdForProfile($organizationId, $staffProfileId);
    }
}
