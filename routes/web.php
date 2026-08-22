<?php

declare(strict_types=1);

use App\Http\Controllers\Portal\GuardianAttendanceController;
use App\Http\Controllers\Portal\GuardianDashboardController;
use App\Http\Controllers\Portal\GuardianReportsController;
use App\Http\Controllers\Portal\StudentAssignmentsController;
use App\Http\Controllers\Portal\StudentDashboardController;
use App\Http\Controllers\Portal\StudentReportsController;
use App\Http\Controllers\Portal\StudentScheduleController;
use App\Http\Controllers\Portal\StudentSessionController;
use App\Http\Controllers\Portal\TeacherDashboardController;
use App\Http\Controllers\Portal\TeacherPostponementsController;
use App\Http\Controllers\Portal\TeacherScheduleController;
use App\Http\Controllers\Portal\TeacherSessionController;
use App\Http\Controllers\UpdateLocaleController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::get('/', function (Request $request) {
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
})->name('home');

Route::middleware(['auth'])->group(function (): void {
    Route::post('/locale', UpdateLocaleController::class)
        ->name('locale.update');

    Route::get('/student', StudentDashboardController::class)
        ->middleware('can:session.view')
        ->name('portal.student.dashboard');
    Route::get('/student/schedule', StudentScheduleController::class)
        ->middleware('can:schedule.view')
        ->name('portal.student.schedule');
    Route::get('/student/sessions/{id}', StudentSessionController::class)
        ->middleware('can:session.view')
        ->name('portal.student.sessions.show');
    Route::get('/student/assignments', StudentAssignmentsController::class)
        ->middleware('can:assignment.submit')
        ->name('portal.student.assignments.index');
    Route::get('/student/reports', StudentReportsController::class)
        ->middleware('can:session_report.view')
        ->name('portal.student.reports.index');

    Route::get('/teacher', TeacherDashboardController::class)
        ->middleware('can:session.view')
        ->name('portal.teacher.dashboard');
    Route::get('/teacher/schedule', TeacherScheduleController::class)
        ->middleware('can:schedule.view')
        ->name('portal.teacher.schedule');
    Route::get('/teacher/sessions/{id}', TeacherSessionController::class)
        ->middleware('can:attendance.record')
        ->name('portal.teacher.sessions.show');
    Route::get('/teacher/postponements', TeacherPostponementsController::class)
        ->middleware('can:session.postpone.approve')
        ->name('portal.teacher.postponements.index');

    Route::get('/guardian', GuardianDashboardController::class)
        ->middleware('can:student.view')
        ->name('portal.guardian.dashboard');
    Route::get('/guardian/children/{studentId}/attendance', GuardianAttendanceController::class)
        ->middleware('can:attendance.view')
        ->name('portal.guardian.children.attendance');
    Route::get('/guardian/children/{studentId}/reports', GuardianReportsController::class)
        ->middleware('can:session_report.view')
        ->name('portal.guardian.children.reports');
});
