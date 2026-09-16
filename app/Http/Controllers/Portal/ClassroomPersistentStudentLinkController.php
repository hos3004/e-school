<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Application\Actions\EnterClassroom;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Modules\Audit\Domain\Contracts\AuditRecorder;
use Modules\Sessions\Domain\Contracts\SessionAdministrationQueries;
use Modules\VirtualClassroom\Domain\Contracts\ClassroomAdministrationQueries;
use Modules\VirtualClassroom\Domain\Enums\JoinRole;
use Modules\VirtualClassroom\Domain\ValueObjects\RoomIdentity;
use Shared\Support\BusinessRuleViolation;
use Symfony\Component\HttpFoundation\Response;

/**
 * الرابط الدائم الذي يرسله المعلم للطالب مرة واحدة لكل جدول متكرر.
 *
 * على عكس classroom.student-link (توقيع classroom.student-link — يخص حصة
 * واحدة وينتهي بنهاية نافذة دخولها)، هذا الرابط ثابت طول عمر الجدول: يُحل
 * كل مرة إلى الحصة القابلة للدخول الآن على هذا الجدول — العادية أو حصة
 * التلافي القائمة مقام حصة ملغاة منه.
 *
 * الإبطال بعد تدوير إداري لا يعتمد على توقيع Laravel وحده — ذلك التوقيع
 * يبقى صالحًا رياضيًا لأن الرابط بلا تاريخ انتهاء. الإبطال الفعلي بمطابقة
 * جيل الرابط (v) الذي يحمله الرابط مع الجيل الحالي المخزَّن على الغرفة؛
 * تدوير الرابط يرفع الجيل فورًا فيسقط أي رابط منسوخ سابقًا قبل حتى إعادة
 * تجهيز الغرفة عند المزوّد.
 */
final class ClassroomPersistentStudentLinkController
{
    public function __construct(
        private readonly EnterClassroom $enterClassroom,
        private readonly SessionAdministrationQueries $sessions,
        private readonly ClassroomAdministrationQueries $classrooms,
        private readonly AuditRecorder $audit,
    ) {}

    public function __invoke(Request $request, string $schedule, string $enrollment): Response
    {
        abort_unless((bool) config('virtual-classroom.student_link.enabled'), 404);

        $requestedGeneration = (int) $request->query('v', '1');
        $currentGeneration = $this->classrooms->generationForRoomIdentity(RoomIdentity::forSchedule($schedule));

        // رابط منسوخ قبل تدوير إداري: يُرفض دون كشف أي تفاصيل عن السبب.
        abort_if($requestedGeneration !== $currentGeneration, 404);

        $scheduleRow = DB::table('schedules')->where('id', $schedule)->first(['id', 'organization_id']);
        abort_if($scheduleRow === null, 404);

        $organizationId = (string) $scheduleRow->organization_id;
        $beforeMinutes = (int) config('virtual-classroom.join_window.before_minutes');
        $afterMinutes = (int) config('virtual-classroom.join_window.after_minutes');

        $current = $this->sessions->currentJoinableForSchedule(
            $organizationId,
            $schedule,
            CarbonImmutable::now('UTC'),
            $beforeMinutes,
            $afterMinutes,
        );

        if ($current === null) {
            return Inertia::render('Errors/ClassroomLink', [
                'reason' => __('virtualclassroom::messages.no_session_now'),
            ])->toResponse($request)->setStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $row = DB::table('session_participants')
            ->join('sessions', 'sessions.id', '=', 'session_participants.session_id')
            ->join('student_profiles', 'student_profiles.id', '=', 'session_participants.student_profile_id')
            ->join('enrollments', 'enrollments.id', '=', 'session_participants.enrollment_id')
            ->join('users as student_users', 'student_users.id', '=', 'student_profiles.user_id')
            ->where('session_participants.session_id', $current->id)
            ->where('session_participants.enrollment_id', $enrollment)
            ->whereColumn('student_profiles.organization_id', 'sessions.organization_id')
            ->whereColumn('enrollments.organization_id', 'sessions.organization_id')
            ->whereColumn('student_users.organization_id', 'sessions.organization_id')
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
                'sessions.organization_id',
                'session_participants.id as participant_id',
                'enrollments.frozen_at',
                'student_profiles.user_id as student_user_id',
                'student_users.name as student_name',
            ]);

        abort_if($row === null, 404);

        $studentUserId = (string) $row->student_user_id;

        try {
            $url = $this->enterClassroom->url(
                row: $row,
                organizationId: $organizationId,
                userId: $studentUserId,
                displayName: (string) $row->student_name,
                role: JoinRole::Viewer,
                isFrozen: $row->frozen_at !== null,
                isTeacher: false,
            );
        } catch (BusinessRuleViolation $violation) {
            return Inertia::render('Errors/ClassroomLink', ['reason' => $violation->getMessage()])
                ->toResponse($request)
                ->setStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->audit->record(
            $organizationId,
            $studentUserId,
            'user',
            'classroom.persistent_student_link.used',
            'session_participants',
            (string) $row->participant_id,
            null,
            ['session_id' => (string) $row->id, 'schedule_id' => $schedule, 'entry' => 'persistent_student_link'],
            __('virtualclassroom::messages.persistent_student_link_audit_reason'),
        );

        return redirect()->away($url);
    }
}
