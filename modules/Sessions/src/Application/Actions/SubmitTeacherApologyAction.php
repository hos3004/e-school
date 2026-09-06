<?php

declare(strict_types=1);

namespace Modules\Sessions\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\DB;
use Modules\Audit\Domain\Contracts\AuditRecorder;
use Modules\Sessions\Domain\Enums\ApologyStatus;
use Modules\Sessions\Domain\Enums\SessionStatus;
use Modules\Sessions\Domain\Events\TeacherApologySubmitted;
use Modules\Sessions\Domain\Models\Session;
use Modules\Sessions\Domain\Models\TeacherApology;
use Modules\Staff\Domain\Contracts\StaffQueries;
use Shared\Support\BusinessRuleViolation;

/**
 * تقديم المعلم اعتذارًا عن حصة.
 *
 * التقديم لا يلغي الحصة ولا يفرّغ المعلم منها. يُعتمد الاعتذار تلقائيًا في
 * نفس المسار، ثم يبدأ البحث الفوري والدوري عن بديل بلا موافقة إدارية
 * (docs/client-answers.md §ي).
 *
 * المهلة إلزامية؛ الاعتذار داخل الساعة الأخيرة مرفوض كقاعدة عمل.
 */
final readonly class SubmitTeacherApologyAction
{
    public function __construct(
        private Dispatcher $events,
        private AuditRecorder $audit,
        private StaffQueries $staff,
        private DecideTeacherApologyAction $decisions,
    ) {}

    public function execute(
        string $sessionId,
        string $staffProfileId,
        string $reason,
        ?CarbonImmutable $now = null,
    ): TeacherApology {
        $now ??= CarbonImmutable::now('UTC');

        if (trim($reason) === '') {
            throw BusinessRuleViolation::make(
                'sessions.apology_reason_required',
                'sessions::errors.apology_reason_required',
            );
        }

        $session = Session::query()->findOrFail($sessionId);

        $this->guardSessionAcceptsApology($session);

        // المعلم الفعلي وحده هو من يعتذر. البديل المسند يعتذر عن نفسه لا عن الأصلي.
        if ((string) $session->staff_profile_id !== $staffProfileId) {
            throw BusinessRuleViolation::make(
                'sessions.apology_not_assigned_teacher',
                'sessions::errors.apology_not_assigned_teacher',
            );
        }

        $alreadyOpen = TeacherApology::query()
            ->where('session_id', $sessionId)
            ->where('staff_profile_id', $staffProfileId)
            ->whereIn('status', [ApologyStatus::Submitted, ApologyStatus::Approved])
            ->exists();

        if ($alreadyOpen) {
            throw BusinessRuleViolation::make(
                'sessions.apology_already_pending',
                'sessions::errors.apology_already_pending',
            );
        }

        $noticeMinutes = (int) $now->diffInMinutes(
            CarbonImmutable::instance($session->scheduled_start),
            false,
        );

        $minNotice = (int) config('scheduling.apology.min_notice_minutes');
        if ($noticeMinutes < $minNotice) {
            throw BusinessRuleViolation::make(
                'sessions.apology_notice_not_met',
                'sessions::errors.apology_notice_not_met',
                ['required' => $minNotice, 'actual' => max(0, $noticeMinutes)],
            );
        }

        $actorId = $this->teacherUserId((string) $session->organization_id, $staffProfileId);
        $apology = DB::transaction(function () use ($session, $staffProfileId, $reason, $now, $noticeMinutes, $minNotice, $actorId): TeacherApology {
            $apology = new TeacherApology;
            $apology->fill([
                'organization_id' => (string) $session->organization_id,
                'session_id' => (string) $session->id,
                'staff_profile_id' => $staffProfileId,
                'status' => ApologyStatus::Submitted->value,
                'reason' => trim($reason),
                'submitted_at' => $now,
                'notice_minutes' => $noticeMinutes,
                'is_late_notice' => $noticeMinutes < $minNotice,
            ]);
            $apology->save();

            $this->audit->record(
                organizationId: (string) $session->organization_id,
                actorId: $actorId,
                actorType: $actorId === null ? 'system' : 'user',
                action: 'sessions.teacher_apology_submitted',
                auditableType: 'teacher_apologies',
                auditableId: (string) $apology->getKey(),
                oldValues: null,
                newValues: [
                    'session_id' => (string) $session->getKey(),
                    'staff_profile_id' => $staffProfileId,
                    'status' => ApologyStatus::Submitted->value,
                    'is_late_notice' => $noticeMinutes < $minNotice,
                    'notice_minutes' => $noticeMinutes,
                ],
                reason: trim($reason),
            );

            return $apology;
        });

        $this->events->dispatch(new TeacherApologySubmitted(
            sessionId: (string) $session->id,
            organizationId: (string) $session->organization_id,
            courseId: (string) $session->course_id,
            staffProfileId: $staffProfileId,
            apologyId: (string) $apology->id,
            teacherUserId: $actorId,
            isLateNotice: (bool) $apology->is_late_notice,
            noticeMinutes: $noticeMinutes,
            scheduledStart: CarbonImmutable::instance($session->scheduled_start)->toIso8601String(),
            actorId: $actorId,
        ));

        if ($actorId === null) {
            throw BusinessRuleViolation::make(
                'sessions.apology_teacher_account_missing',
                'sessions::errors.apology_teacher_account_missing',
            );
        }

        return $this->decisions->approve(
            apologyId: (string) $apology->id,
            decidedBy: $actorId,
            decisionReason: trim($reason),
            now: $now,
            expectedOrganizationId: (string) $session->organization_id,
            expectedSessionId: (string) $session->id,
        );
    }

    /**
     * معرّف مستخدم المعلم — يحتاجه موديول Notifications ليعرف لمن يرسل.
     *
     * عقد Staff يعيد هوية المستخدم دون تسريب نموذج أو جدول الموديول المالك.
     */
    private function teacherUserId(string $organizationId, string $staffProfileId): ?string
    {
        return $this->staff->userIdForProfile($organizationId, $staffProfileId);
    }

    /**
     * لا يُعتذر عن حصة انتهت أو أُلغيت أو جارية بالفعل.
     */
    private function guardSessionAcceptsApology(Session $session): void
    {
        $blocked = [
            SessionStatus::Completed,
            SessionStatus::CancelledByStudent,
            SessionStatus::CancelledByTeacher,
            SessionStatus::CancelledBySchool,
            SessionStatus::Postponed,
        ];

        foreach ($blocked as $status) {
            if ($session->status === $status) {
                throw BusinessRuleViolation::make(
                    'sessions.apology_session_closed',
                    'sessions::errors.apology_session_closed',
                    ['status' => $session->status->value],
                );
            }
        }
    }
}
