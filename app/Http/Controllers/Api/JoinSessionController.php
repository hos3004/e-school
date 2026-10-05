<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Application\Actions\EnterClassroom;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\VirtualClassroom\Domain\Enums\JoinRole;

/**
 * دخول الفصل للموبايل (توكن) — يرجّع رابط الدخول بدل التحويل المباشر
 * (redirect) اللي تستخدمه بوابة الويب Portal\ClassroomJoinController.
 * نفس الاستعلام والفحوص وEnterClassroom بالضبط، فقط شكل الرد مختلف.
 */
final class JoinSessionController extends Controller
{
    public function __construct(
        private readonly EnterClassroom $enterClassroom,
    ) {}

    public function teacher(Request $request, string $session): JsonResponse
    {
        $user = $request->user();
        $organizationId = (string) $user?->getAttribute('organization_id');
        $userId = (string) $user?->getAuthIdentifier();

        $row = DB::table('sessions')
            ->join('staff_profiles', 'staff_profiles.id', '=', 'sessions.staff_profile_id')
            ->leftJoin('schedules', 'schedules.id', '=', 'sessions.schedule_id')
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
                'schedules.flexible_start as schedule_flexible_start',
            ]);

        abort_if($row === null, 404);

        $url = $this->enterClassroom->url(
            row: $row,
            organizationId: $organizationId,
            userId: $userId,
            displayName: (string) $user?->getAttribute('name'),
            role: JoinRole::Moderator,
            isFrozen: false,
            isTeacher: true,
            // WebView الموبايل يعترض هذا الرابط بالذات ويقفل شاشة الفصل
            // بدل ما يترك BBB يعرض صفحته الافتراضية بعد المغادرة.
            returnUrl: route('mobile.classroom.left'),
        );

        return response()->json(['join_url' => $url]);
    }

    public function student(Request $request, string $session): JsonResponse
    {
        $user = $request->user();
        $organizationId = (string) $user?->getAttribute('organization_id');
        $userId = (string) $user?->getAuthIdentifier();

        $row = DB::table('sessions')
            ->join('session_participants', 'session_participants.session_id', '=', 'sessions.id')
            ->join('student_profiles', 'student_profiles.id', '=', 'session_participants.student_profile_id')
            ->join('enrollments', 'enrollments.id', '=', 'session_participants.enrollment_id')
            ->leftJoin('schedules', 'schedules.id', '=', 'sessions.schedule_id')
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
                'schedules.flexible_start as schedule_flexible_start',
                'enrollments.frozen_at',
            ]);

        abort_if($row === null, 404);

        $url = $this->enterClassroom->url(
            row: $row,
            organizationId: $organizationId,
            userId: $userId,
            displayName: (string) $user?->getAttribute('name'),
            role: JoinRole::Viewer,
            isFrozen: $row->frozen_at !== null,
            isTeacher: false,
            returnUrl: route('mobile.classroom.left'),
        );

        return response()->json(['join_url' => $url]);
    }
}
