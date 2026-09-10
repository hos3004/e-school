<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Application\Actions\EnterClassroom;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Modules\Audit\Domain\Contracts\AuditRecorder;
use Modules\VirtualClassroom\Domain\Enums\JoinRole;
use Shared\Support\BusinessRuleViolation;
use Symfony\Component\HttpFoundation\Response;

/**
 * رابط دخول الطالب اليدوي الذي ينسخه المعلم ويرسله للطالب.
 *
 * سبب وجوده: الطالب الذي تعذّر دخوله لحسابه يحتاج طريقًا بديلًا للحصة.
 * هذا المسار موقّع ومربوط بالطالب نفسه (session + participant داخل التوقيع)،
 * فيبقى الحضور محسوبًا لصاحبه لأن رابط المزوّد يحمل معرّف مستخدم الطالب،
 * وتبقى نافذة الدخول وحالة الحصة وتجميد القيد مطبَّقة كما في البوابة.
 *
 * لا يمنح الرابط جلسة ولا يفتح أي صفحة داخل المنصة؛ يوجّه إلى الفصل فقط.
 */
final class ClassroomStudentLinkController
{
    public function __construct(
        private readonly EnterClassroom $enterClassroom,
        private readonly AuditRecorder $audit,
    ) {}

    public function __invoke(Request $request, string $session, string $participant): Response
    {
        // مفتاح الإيقاف يجب أن يبطل الروابط المنسوخة سلفًا، لا أن يمنع توليد
        // روابط جديدة فقط، وإلا بقيت الميزة تعمل بعد إغلاقها.
        abort_unless((bool) config('virtual-classroom.student_link.enabled'), 404);

        $row = DB::table('session_participants')
            ->join('sessions', 'sessions.id', '=', 'session_participants.session_id')
            ->join('student_profiles', 'student_profiles.id', '=', 'session_participants.student_profile_id')
            ->join('enrollments', 'enrollments.id', '=', 'session_participants.enrollment_id')
            ->join('users as student_users', 'student_users.id', '=', 'student_profiles.user_id')
            ->where('session_participants.id', $participant)
            ->where('session_participants.session_id', $session)
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

        $organizationId = (string) $row->organization_id;
        $studentUserId = (string) $row->student_user_id;

        /*
         * الطالب هنا بلا جلسة، والرجوع الافتراضي لمخالفة قاعدة العمل يقذفه إلى
         * الصفحة الرئيسية بلا تفسير. نعرض له سبب المنع مترجمًا في صفحة مستقلة.
         */
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
            'classroom.student_link.used',
            'session_participants',
            (string) $row->participant_id,
            null,
            ['session_id' => (string) $row->id, 'entry' => 'manual_student_link'],
            __('virtualclassroom::messages.student_link_audit_reason'),
        );

        return redirect()->away($url);
    }
}
