<?php

declare(strict_types=1);

namespace Modules\Sessions\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Modules\Sessions\Domain\Contracts\SessionSchedulingGateway;
use Modules\Sessions\Domain\Events\SessionPostponed;
use Modules\Sessions\Domain\Models\Session;
use Shared\Support\BusinessRuleViolation;

/**
 * تأجيل حصة مع إنشاء حصة تلافي مرتبطة بها عبر makeup_for_session_id.
 *
 * قاعدة المهلة: التأجيل مسموح فقط قبل مهلة الإشعار المعرّفة في
 * config('scheduling.notice.postponement_minutes') — لا رقم داخل الكود.
 */
final readonly class PostponeSessionAction
{
    public function __construct(
        private Dispatcher $events,
        private SessionSchedulingGateway $scheduling,
    ) {}

    public function execute(Session $session, string $makeupStart, string $makeupEnd, string $reason, string $actorId): Session
    {
        $newStart = CarbonImmutable::parse($makeupStart, 'UTC');
        $newEnd = CarbonImmutable::parse($makeupEnd, 'UTC');

        if ($newStart->lessThan(CarbonImmutable::now('UTC'))) {
            throw BusinessRuleViolation::make(
                'sessions.makeup_in_past',
                'sessions::errors.start_in_past',
            );
        }

        if ($newEnd->lessThanOrEqualTo($newStart)) {
            throw BusinessRuleViolation::make(
                'sessions.makeup_end_before_start',
                'sessions::errors.end_before_start',
            );
        }

        $expectedEnd = $newStart->addMinutes((int) $session->scheduled_start->diffInMinutes($session->scheduled_end));
        if (!$newEnd->equalTo($expectedEnd)) {
            throw BusinessRuleViolation::make(
                'sessions.makeup_duration_changed',
                'sessions::errors.makeup_duration_changed',
            );
        }

        $noticeMinutes = (int) config('scheduling.notice.postponement_minutes');

        if (CarbonImmutable::now('UTC')->greaterThan(CarbonImmutable::instance($session->scheduled_start)->subMinutes($noticeMinutes))) {
            throw BusinessRuleViolation::make(
                'sessions.postponement_window_passed',
                'sessions::errors.postponement_window_passed',
                ['minutes' => $noticeMinutes],
            );
        }

        $makeupId = $this->scheduling->scheduleMakeup(
            organizationId: (string) $session->organization_id,
            originalSessionId: (string) $session->getKey(),
            startsAt: $newStart,
            actorId: $actorId,
            reason: $reason,
        );

        $this->events->dispatch(new SessionPostponed(
            sessionId: (string) $session->id,
            organizationId: (string) $session->organization_id,
            courseId: (string) $session->course_id,
            staffProfileId: (string) $session->staff_profile_id,
            makeupSessionId: $makeupId,
            makeupStart: $newStart->toIso8601String(),
            makeupEnd: $newEnd->toIso8601String(),
            reason: $reason,
        ));

        return $session->refresh();
    }
}
