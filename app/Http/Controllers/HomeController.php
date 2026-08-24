<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * نقطة الدخول بعد تسجيل الدخول: تعيد توجيه كل دور إلى بوابته وفق
 * الصلاحيات والملف الشخصي — لا فحص على اسم دور في أي مكان.
 */
final class HomeController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        if ($user === null) {
            return redirect()->route('login');
        }

        $userId = (string) $user->getAuthIdentifier();
        $organizationId = (string) $user->getAttribute('organization_id');

        if ($user->can('attendance.record') && DB::table('staff_profiles')
            ->where('user_id', $userId)
            ->where('organization_id', $organizationId)
            ->whereNull('deleted_at')
            ->exists()) {
            return redirect()->route('portal.teacher.dashboard');
        }

        if ($user->can('assignment.submit') && DB::table('student_profiles')
            ->where('user_id', $userId)
            ->where('organization_id', $organizationId)
            ->whereNull('deleted_at')
            ->exists()) {
            return redirect()->route('portal.student.dashboard');
        }

        if ($user->can('student.view') && DB::table('guardian_profiles')
            ->join('guardian_links', 'guardian_links.guardian_profile_id', '=', 'guardian_profiles.id')
            ->join('student_profiles', 'student_profiles.id', '=', 'guardian_links.student_profile_id')
            ->join('users as student_users', 'student_users.id', '=', 'student_profiles.user_id')
            ->where('guardian_profiles.user_id', $userId)
            ->where('guardian_profiles.organization_id', $organizationId)
            ->whereColumn('student_profiles.organization_id', 'guardian_profiles.organization_id')
            ->whereColumn('student_users.organization_id', 'guardian_profiles.organization_id')
            ->whereNull('guardian_profiles.deleted_at')
            ->whereNull('student_profiles.deleted_at')
            ->whereNull('student_users.deleted_at')
            ->whereNotNull('guardian_links.verified_at')
            ->exists()) {
            return redirect()->route('portal.guardian.dashboard');
        }

        if ($user->can('settings.manage')) {
            return redirect()->route('filament.admin.pages.dashboard');
        }

        abort(403);
    }
}
