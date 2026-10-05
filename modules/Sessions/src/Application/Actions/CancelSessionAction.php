<?php

declare(strict_types=1);

namespace Modules\Sessions\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Modules\Sessions\Domain\Enums\SessionStatus;
use Modules\Sessions\Domain\Events\SessionCancelled;
use Modules\Sessions\Domain\Models\Session;
use Shared\Support\BusinessRuleViolation;

/**
 * إلغاء حصة — من الطالب أو المعلم أو الإدارة.
 *
 * قاعدة المهلة: إلغاء الطالب مسموح فقط قبل مهلة الإشعار المعرّفة في
 * config('scheduling.notice.cancellation_minutes') — لا رقم داخل الكود.
 */
final readonly class CancelSessionAction
{
    public function __construct(
        private Dispatcher $events,
        private TransitionSessionStatusAction $transition,
    ) {}

    public function execute(Session $session, SessionStatus $as, string $reason, string $actorId): Session
    {
        if (!in_array($as, [SessionStatus::CancelledByStudent, SessionStatus::CancelledByTeacher, SessionStatus::CancelledBySchool], true)) {
            throw BusinessRuleViolation::make(
                'sessions.cancel_target_invalid',
                'sessions::errors.cancel_target_invalid',
            );
        }

        if ($as === SessionStatus::CancelledByStudent) {
            $noticeMinutes = (int) config('scheduling.notice.cancellation_minutes');

            $deadline = CarbonImmutable::instance($session->scheduled_start)->subMinutes($noticeMinutes);

            if (CarbonImmutable::now('UTC')->greaterThan($deadline)) {
                throw BusinessRuleViolation::make(
                    'sessions.cancellation_window_passed',
                    'sessions::errors.cancellation_window_passed',
                    ['minutes' => $noticeMinutes],
                );
            }
        }

        $now = CarbonImmutable::now('UTC');

        $session = $this->transition->execute(
            $session,
            $as,
            $actorId,
            $reason,
            'sessions.session_cancelled',
            [
                'cancelled_by' => $actorId,
                'cancelled_at' => $now->toIso8601String(),
                'cancellation_reason' => $reason,
            ],
            ['cancelled_as' => $as->value],
        );

        $this->events->dispatch(new SessionCancelled(
            sessionId: $session->id,
            organizationId: $session->organization_id,
            courseId: $session->course_id,
            staffProfileId: $session->staff_profile_id,
            cancelledAs: $as,
            cancelledAt: $now->toIso8601String(),
            cancelledById: $session->cancelled_by,
            reason: $reason,
        ));

        return $session;
    }
}
