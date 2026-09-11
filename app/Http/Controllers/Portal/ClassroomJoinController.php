<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Application\Actions\EnterClassroom;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\VirtualClassroom\Domain\Enums\JoinRole;

/**
 * بوابة الدخول الوحيدة إلى الفصل من بوابات الطالب والمعلم.
 *
 * لا نرسل رابط BBB الموقّع إلى Inertia: هذا المتحكّم يعيد التحقق من نطاق
 * الحصة ونافذة الدخول عند كل نقرة، ثم يوجّه المتصفح إلى رابط قصير العمر.
 */
final class ClassroomJoinController
{
    public function __construct(
        private readonly EnterClassroom $enterClassroom,
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

        return redirect()->away($this->enterClassroom->url(
            row: $row,
            organizationId: $organizationId,
            userId: $userId,
            displayName: (string) $user?->getAttribute('name'),
            role: JoinRole::Moderator,
            isFrozen: false,
            isTeacher: true,
            returnUrl: $this->returnUrl($request, $session, isTeacher: true),
        ));
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

        return redirect()->away($this->enterClassroom->url(
            row: $row,
            organizationId: $organizationId,
            userId: $userId,
            displayName: (string) $user?->getAttribute('name'),
            role: JoinRole::Viewer,
            isFrozen: $row->frozen_at !== null,
            isTeacher: false,
            returnUrl: $this->returnUrl($request, $session, isTeacher: false),
        ));
    }

    /**
     * وجهة العودة بعد مغادرة الفصل أو انتهاء الحصة.
     *
     * المعلم يعود إلى صفحة الحصة — وفيها نموذج التقرير — بدل صفحة المزوّد،
     * والطالب يعود إلى واجهته. هذا المتحكّم يخدم بوابة /learn والمسارات
     * السابقة معًا، فنشتق الوجهة من اسم المسار الداخل بدل تثبيت بوابة واحدة.
     * تحويل البوابة الأساسية بعدها — إن كان مفعّلًا — يبقى شأن وسيطه هو.
     *
     * وسيط استعلام لا مرساة: BigBlueButton يُلحق سبب الخروج بالرابط نصيًا،
     * بـ'&' حين يوجد استعلام سلفًا و'?' حين لا يوجد — تحقّقنا من الحالتين على
     * الخادم الفعلي. فمرساة '#report' كانت تصير '#report?reason=...' ولا تطابق
     * أي عنصر، بينما 'focus=report' يصل سليمًا فتنقل الصفحة المعلم إلى النموذج.
     *
     * المسارات السابقة تعود إلى صفحتها بلا الوسيط لأن صفحتها لا تقرأه.
     */
    private function returnUrl(Request $request, string $session, bool $isTeacher): string
    {
        $legacy = str_starts_with((string) $request->route()?->getName(), 'portal.');

        if (!$isTeacher) {
            return route($legacy ? 'portal.student.dashboard' : 'learning.student.dashboard');
        }

        return $legacy
            ? route('portal.teacher.sessions.show', ['id' => $session])
            : route('learning.teacher.sessions.show', ['session' => $session, 'focus' => 'report']);
    }
}
