<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\PublicStudentRegistrationController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Portal\GuardianAttendanceController;
use App\Http\Controllers\Portal\GuardianChildController;
use App\Http\Controllers\Portal\GuardianDashboardController;
use App\Http\Controllers\Portal\GuardianReportsController;
use App\Http\Controllers\Portal\GuardianScheduleController;
use App\Http\Controllers\Portal\PortalNotificationsController;
use App\Http\Controllers\Portal\PortalProfileController;
use App\Http\Controllers\Portal\StudentAssignmentsController;
use App\Http\Controllers\Portal\StudentAssignmentSubmissionController;
use App\Http\Controllers\Portal\StudentDashboardController;
use App\Http\Controllers\Portal\StudentGroupController;
use App\Http\Controllers\Portal\StudentProfileController;
use App\Http\Controllers\Portal\StudentProgramsController;
use App\Http\Controllers\Portal\StudentReportsController;
use App\Http\Controllers\Portal\StudentScheduleController;
use App\Http\Controllers\Portal\StudentSessionController;
use App\Http\Controllers\Portal\TeacherAvailabilityController;
use App\Http\Controllers\Portal\TeacherAvailabilityWriteController;
use App\Http\Controllers\Portal\TeacherDashboardController;
use App\Http\Controllers\Portal\TeacherEarningsController;
use App\Http\Controllers\Portal\TeacherGroupsController;
use App\Http\Controllers\Portal\TeacherPostponementsController;
use App\Http\Controllers\Portal\TeacherProfileController;
use App\Http\Controllers\Portal\TeacherScheduleController;
use App\Http\Controllers\Portal\TeacherSessionController;
use App\Http\Controllers\Portal\TeacherStudentsController;
use App\Http\Controllers\UpdateLocaleController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::get('/register/student', [PublicStudentRegistrationController::class, 'showForm'])->name('register.student');
Route::post('/register/student', [PublicStudentRegistrationController::class, 'store'])->name('register.student.store');
Route::get('/register/submitted', [PublicStudentRegistrationController::class, 'showSubmitted'])->name('register.submitted');
Route::get('/register/status/{id}', [PublicStudentRegistrationController::class, 'showStatus'])->name('register.status');

Route::middleware(['auth', 'auth.session'])->group(function (): void {
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
    Route::get('/student/profile', StudentProfileController::class)
        ->name('portal.student.profile');
    Route::get('/student/programs', StudentProgramsController::class)
        ->middleware('can:enrollment.view')
        ->name('portal.student.programs');
    Route::get('/student/group', StudentGroupController::class)
        ->middleware('can:group.view')
        ->name('portal.student.group');
    Route::get('/student/notifications', [PortalNotificationsController::class, 'student'])
        ->name('portal.student.notifications');

    /*
     * كتابة الطالب.
     *
     * البوابات تعمل داخل `web` بجلسة وCSRF، بينما مسارات الموديولات خلف
     * `auth:sanctum` وتُرجع JSON لا تفهمه Inertia. هذه المسارات تستدعي نفس
     * Application Actions وتُرجع redirect مع flash.
     */
    Route::post('/student/assignments/{assignment}/submit', StudentAssignmentSubmissionController::class)
        ->whereUlid('assignment')
        ->name('portal.student.assignments.submit');

    Route::patch('/student/profile', [PortalProfileController::class, 'update'])
        ->name('portal.student.profile.update');

    Route::put('/student/profile/password', [PortalProfileController::class, 'password'])
        ->name('portal.student.profile.password');

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
    Route::get('/teacher/profile', TeacherProfileController::class)
        ->name('portal.teacher.profile');
    Route::get('/teacher/groups', TeacherGroupsController::class)
        ->middleware('can:schedule.view')
        ->name('portal.teacher.groups');
    Route::get('/teacher/students', TeacherStudentsController::class)
        ->middleware('can:student.view')
        ->name('portal.teacher.students');
    Route::get('/teacher/availability', TeacherAvailabilityController::class)
        ->name('portal.teacher.availability');
    Route::get('/teacher/notifications', [PortalNotificationsController::class, 'teacher'])
        ->name('portal.teacher.notifications');

    /*
     * كشف أجر المعلم (ADR-017) — عرض فقط، بلا أي إجراء صرف.
     * المسار لا يُسجَّل أصلًا حين تكون ميزة الأجر مطفأة، فلا يبقى URL معلَّق.
     */
    if ((bool) config('features.payroll')) {
        Route::get('/teacher/earnings', TeacherEarningsController::class)
            ->middleware('can:payroll.view')
            ->name('portal.teacher.earnings');
    }

    /*
     * كتابة المعلم.
     *
     * الإتاحة تُكتب على ملف المعلم المشتق من الجلسة، لا على `staff_profile_id`
     * مرسل من الواجهة — انظر TeacherAvailabilityWriteController.
     */
    Route::post('/teacher/availability', [TeacherAvailabilityWriteController::class, 'store'])
        ->name('portal.teacher.availability.store');

    Route::delete('/teacher/availability/{availability}', [TeacherAvailabilityWriteController::class, 'destroy'])
        ->whereUlid('availability')
        ->name('portal.teacher.availability.destroy');

    Route::patch('/teacher/profile', [PortalProfileController::class, 'update'])
        ->name('portal.teacher.profile.update');

    Route::put('/teacher/profile/password', [PortalProfileController::class, 'password'])
        ->name('portal.teacher.profile.password');

    Route::get('/guardian', GuardianDashboardController::class)
        ->middleware('can:student.view')
        ->name('portal.guardian.dashboard');
    Route::get('/guardian/children/{studentId}/attendance', GuardianAttendanceController::class)
        ->middleware('can:attendance.view')
        ->name('portal.guardian.children.attendance');
    Route::get('/guardian/children/{studentId}/reports', GuardianReportsController::class)
        ->middleware('can:session_report.view')
        ->name('portal.guardian.children.reports');
    Route::get('/guardian/children/{studentId}', GuardianChildController::class)
        ->middleware('can:student.view')
        ->name('portal.guardian.children.show');
    Route::get('/guardian/children/{studentId}/schedule', GuardianScheduleController::class)
        ->middleware('can:schedule.view')
        ->name('portal.guardian.children.schedule');
    Route::get('/guardian/notifications', [PortalNotificationsController::class, 'guardian'])
        ->name('portal.guardian.notifications');
});
