<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Sessions\Domain\Enums\SessionStatus;
use Modules\VirtualClassroom\Application\Actions\GenerateJoinUrlAction;
use Modules\VirtualClassroom\Application\Actions\ProvisionClassroomAction;
use Modules\VirtualClassroom\Domain\Enums\JoinRole;
use Shared\Support\BusinessRuleViolation;

/**
 * بوابة الدخول الوحيدة إلى الفصل من بوابات الطالب والمعلم.
 *
 * لا نرسل رابط BBB الموقّع إلى Inertia: هذا المتحكّم يعيد التحقق من نطاق
 * الحصة ونافذة الدخول عند كل نقرة، ثم يوجّه المتصفح إلى رابط قصير العمر.
 */
final class ClassroomJoinController
{
    public function __construct(
        private readonly ProvisionClassroomAction $provisionClassroom,
        private readonly GenerateJoinUrlAction $generateJoinUrl,
    ) {}

    public function teacher(Request $request, string $session): RedirectResponse
    {
        $user = $request->user();
        $organizationId = (string) $user?->getAttribute('organization_id');
        $userId = (string) $user?->getAuthIdentifier();

        $row = DB::table('sessions')
            ->join('staff_profiles', 'staff_profiles.id', '=', 'sessions.staff_profile_id')
            ->where('sessions.id', $session)
            ->where('sessions.organization_id', $organizationId)
            ->where('staff_profiles.organization_id', $organizationId)
            ->where('staff_profiles.user_id', $userId)
            ->whereNull('sessions.deleted_at')
            ->whereNull('staff_profiles.deleted_at')
            ->first([
                'sessions.id',
                'sessions.title',
                'sessions.status',
                'sessions.scheduled_start',
                'sessions.scheduled_end',
            ]);

        abort_if($row === null, 404);

        return $this->redirectToClassroom(
            row: $row,
            organizationId: $organizationId,
            userId: $userId,
            displayName: (string) $user?->getAttribute('name'),
            role: JoinRole::Moderator,
            isFrozen: false,
            isTeacher: true,
        );
    }

    public function student(Request $request, string $session): RedirectResponse
    {
        $user = $request->user();
        $organizationId = (string) $user?->getAttribute('organization_id');
        $userId = (string) $user?->getAuthIdentifier();

        $row = DB::table('sessions')
            ->join('session_participants', 'session_participants.session_id', '=', 'sessions.id')
            ->join('student_profiles', 'student_profiles.id', '=', 'session_participants.student_profile_id')
            ->join('enrollments', 'enrollments.id', '=', 'session_participants.enrollment_id')
            ->where('sessions.id', $session)
            ->where('sessions.organization_id', $organizationId)
            ->where('student_profiles.organization_id', $organizationId)
            ->where('enrollments.organization_id', $organizationId)
            ->where('student_profiles.user_id', $userId)
            ->whereNull('session_participants.revoked_at')
            ->whereNull('session_participants.deleted_at')
            ->whereNull('sessions.deleted_at')
            ->whereNull('student_profiles.deleted_at')
            ->whereNull('enrollments.deleted_at')
            ->first([
                'sessions.id',
                'sessions.title',
                'sessions.status',
                'sessions.scheduled_start',
                'sessions.scheduled_end',
                'enrollments.frozen_at',
            ]);

        abort_if($row === null, 404);

        return $this->redirectToClassroom(
            row: $row,
            organizationId: $organizationId,
            userId: $userId,
            displayName: (string) $user?->getAttribute('name'),
            role: JoinRole::Viewer,
            isFrozen: $row->frozen_at !== null,
            isTeacher: false,
        );
    }

    private function redirectToClassroom(
        object $row,
        string $organizationId,
        string $userId,
        string $displayName,
        JoinRole $role,
        bool $isFrozen,
        bool $isTeacher,
    ): RedirectResponse {
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
        );

        return redirect()->away($this->generateJoinUrl->execute(
            classroom: $classroom,
            userId: $userId,
            displayName: $displayName !== '' ? $displayName : __('virtualclassroom::messages.default_participant_name'),
            role: $role,
            isFrozen: $isFrozen,
        ));
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
