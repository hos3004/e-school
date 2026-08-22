<?php

declare(strict_types=1);

namespace Modules\Sessions\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\DB;
use Modules\Sessions\Domain\Enums\ApologyStatus;
use Modules\Sessions\Domain\Enums\SessionStatus;
use Modules\Sessions\Domain\Events\TeacherApologySubmitted;
use Modules\Sessions\Domain\Models\Session;
use Modules\Sessions\Domain\Models\TeacherApology;
use Shared\Support\BusinessRuleViolation;

/**
 * تقديم المعلم اعتذارًا عن حصة.
 *
 * التقديم وحده **لا يغيّر شيئًا في الحصة**: لا يلغيها ولا يفرّغ المعلم منها
 * ولا يبدأ بحثًا عن بديل. كل ذلك معلّق على اعتماد المشرف
 * (docs/client-answers.md §ي).
 *
 * التقديم المتأخر عن المهلة **مقبول** ويُعلَّم `is_late_notice` ليراه المشرف —
 * لأن الظرف الطارئ لا يُعرّف بمهلة، لكن تكراره يجب أن يكون مرئيًا.
 */
final readonly class SubmitTeacherApologyAction
{
    public function __construct(
        private Dispatcher $events,
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
            ->where('status', ApologyStatus::Submitted)
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

        $minNotice = (int) config('scheduling.apology.min_notice_minutes', 120);

        $apology = DB::transaction(function () use ($session, $staffProfileId, $reason, $now, $noticeMinutes, $minNotice): TeacherApology {
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

            return $apology;
        });

        $this->events->dispatch(new TeacherApologySubmitted(
            sessionId: (string) $session->id,
            organizationId: (string) $session->organization_id,
            courseId: (string) $session->course_id,
            staffProfileId: $staffProfileId,
            apologyId: (string) $apology->id,
            teacherUserId: $this->teacherUserId($staffProfileId),
            isLateNotice: (bool) $apology->is_late_notice,
            noticeMinutes: $noticeMinutes,
            scheduledStart: CarbonImmutable::instance($session->scheduled_start)->toIso8601String(),
            actorId: $this->teacherUserId($staffProfileId),
        ));

        return $apology;
    }

    /**
     * معرّف مستخدم المعلم — يحتاجه موديول Notifications ليعرف لمن يرسل.
     *
     * نقرأ بالجدول لا بنموذج موديول Staff: استيراد نماذج الموديولات الأخرى
     * ممنوع وتفرضه اختبارات المعمارية.
     */
    private function teacherUserId(string $staffProfileId): ?string
    {
        $id = DB::table('staff_profiles')
            ->where('id', $staffProfileId)
            ->value('user_id');

        return is_string($id) && $id !== '' ? $id : null;
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
