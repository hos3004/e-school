<?php

declare(strict_types=1);

namespace App\Application\Actions;

use Carbon\CarbonImmutable;
use Modules\Sessions\Domain\Enums\SessionStatus;
use Modules\VirtualClassroom\Application\Actions\GenerateJoinUrlAction;
use Modules\VirtualClassroom\Application\Actions\ProvisionClassroomAction;
use Modules\VirtualClassroom\Domain\Enums\JoinRole;
use Shared\Support\BusinessRuleViolation;

/**
 * البوابة الوحيدة التي تحوّل صف حصة إلى رابط دخول موقّع من المزوّد.
 *
 * كل مداخل الدخول — بوابة المعلم وبوابة الطالب ورابط الطالب اليدوي —
 * تمر من هنا حتى تُطبَّق نافذة الدخول وحالة الحصة وتجميد القيد مرة واحدة
 * وبنفس القواعد. الرابط لا يُخزَّن ولا يُرسل إلى Inertia.
 */
final readonly class EnterClassroom
{
    public function __construct(
        private ProvisionClassroomAction $provisionClassroom,
        private GenerateJoinUrlAction $generateJoinUrl,
    ) {}

    /**
     * @param object{id: mixed, title: mixed, status: mixed, scheduled_start: mixed, scheduled_end: mixed} $row
     */
    public function url(
        object $row,
        string $organizationId,
        string $userId,
        string $displayName,
        JoinRole $role,
        bool $isFrozen,
        bool $isTeacher,
    ): string {
        $status = SessionStatus::tryFrom((string) $row->status);

        if ($status === null || !$status->allowsJoining()) {
            throw BusinessRuleViolation::make(
                'virtualclassroom.session_not_joinable',
                'virtualclassroom::errors.session_not_joinable',
            );
        }

        $startsAt = CarbonImmutable::parse((string) $row->scheduled_start, 'UTC')->utc();
        $endsAt = CarbonImmutable::parse((string) $row->scheduled_end, 'UTC')->utc();
        $beforeMinutes = $isTeacher
            ? (int) config('virtual-classroom.join_window.teacher_before_minutes')
            : (int) config('virtual-classroom.join_window.before_minutes');
        $afterMinutes = (int) config('virtual-classroom.join_window.after_minutes');
        $now = CarbonImmutable::now('UTC');

        if ($now->lt($startsAt->subMinutes(max(0, $beforeMinutes)))
            || $now->gt($endsAt->addMinutes(max(0, $afterMinutes)))) {
            throw BusinessRuleViolation::make(
                'virtualclassroom.join_window_closed',
                'virtualclassroom::errors.join_window_closed',
            );
        }

        $classroom = $this->provisionClassroom->execute(
            sessionId: (string) $row->id,
            title: $this->localizedTitle($row->title),
            startsAt: $startsAt,
            organizationId: $organizationId,
            actorId: $userId,
            reason: __('virtualclassroom::messages.portal_provision_reason'),
            ensureRemoteIsRunning: true,
        );

        return $this->generateJoinUrl->execute(
            classroom: $classroom,
            userId: $userId,
            displayName: $displayName !== '' ? $displayName : __('virtualclassroom::messages.default_participant_name'),
            role: $role,
            isFrozen: $isFrozen,
        );
    }

    private function localizedTitle(mixed $title): string
    {
        if (is_string($title)) {
            $decoded = json_decode($title, true);
            $title = is_array($decoded) ? $decoded : $title;
        }

        if (!is_array($title)) {
            return (string) $title;
        }

        foreach ([app()->getLocale(), config('app.fallback_locale'), 'ar', 'en'] as $locale) {
            $candidate = $title[$locale] ?? null;

            if (is_string($candidate) && $candidate !== '') {
                return $candidate;
            }
        }

        return __('virtualclassroom::messages.default_classroom_title');
    }
}
