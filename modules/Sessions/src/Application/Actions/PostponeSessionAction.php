<?php

declare(strict_types=1);

namespace Modules\Sessions\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\DB;
use Modules\Sessions\Application\Concerns\TransitionsSessionStatus;
use Modules\Sessions\Domain\Enums\SessionStatus;
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
    use TransitionsSessionStatus;

    public function __construct(
        private Dispatcher $events,
    ) {}

    public function execute(Session $session, string $makeupStart, string $makeupEnd, ?string $reason = null, ?string $actorId = null): Session
    {
        $this->guardNotTerminal($session);

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

        $noticeMinutes = (int) config('scheduling.notice.postponement_minutes');

        if (CarbonImmutable::now('UTC')->greaterThan(CarbonImmutable::instance($session->scheduled_start)->subMinutes($noticeMinutes))) {
            throw BusinessRuleViolation::make(
                'sessions.postponement_window_passed',
                'sessions::errors.postponement_window_passed',
                ['minutes' => $noticeMinutes],
            );
        }

        [$session, $makeup, $event] = DB::transaction(function () use ($session, $newStart, $newEnd, $reason): array {
            $makeup = new Session;
            $makeup->fill([
                'organization_id' => $session->organization_id,
                'schedule_id' => $session->schedule_id,
                'group_id' => $session->group_id,
                'course_id' => $session->course_id,
                'staff_profile_id' => $session->staff_profile_id,
                'substitute_for_staff_id' => $session->substitute_for_staff_id,
                'makeup_for_session_id' => $session->id,
                'session_type' => $session->session_type,
                'status' => SessionStatus::Scheduled,
                'scheduled_start' => $newStart,
                'scheduled_end' => $newEnd,
                'title' => $session->title,
                'notes' => __('sessions::messages.makeup_of', ['title' => $this->sessionTitle($session)]),
            ]);
            $makeup->save();

            $this->applyTransition(
                $session,
                SessionStatus::Postponed,
                [],
                reason: $reason ?? __('sessions::messages.postpone_default_reason'),
            );

            return [$session, $makeup, new SessionPostponed(
                sessionId: $session->id,
                organizationId: $session->organization_id,
                courseId: $session->course_id,
                staffProfileId: $session->staff_profile_id,
                makeupSessionId: $makeup->id,
                makeupStart: $newStart->toIso8601String(),
                makeupEnd: $newEnd->toIso8601String(),
                reason: $reason,
            )];
        });

        $this->events->dispatch($event);

        return $session->refresh();
    }

    private function sessionTitle(Session $session): string
    {
        /** @var array<string, string> $title */
        $title = $session->title;

        return $title[app()->getLocale()] ?? reset($title);
    }
}
