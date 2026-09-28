<?php

declare(strict_types=1);

use App\Http\Controllers\Api\GuardianController;
use App\Http\Controllers\Api\JoinSessionController;
use App\Http\Controllers\Api\StudentPostponementController;
use App\Http\Controllers\Api\StudentProfileController;
use App\Http\Controllers\Api\StudentSessionController;
use App\Http\Controllers\Api\TeacherAvailabilityController;
use App\Http\Controllers\Api\TeacherEarningsController;
use App\Http\Controllers\Api\TeacherGroupController;
use App\Http\Controllers\Api\TeacherPostponementController;
use App\Http\Controllers\Api\TeacherProfileController;
use App\Http\Controllers\Api\TeacherRequiredReportsController;
use App\Http\Controllers\Api\TeacherSessionController;
use App\Http\Controllers\Api\TeacherStudentController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
| مسارات API لكل موديول تُحمَّل من modules/<Name>/routes/api.php
| عقود الـ API موثّقة في docs/10-api-contracts.md
*/

Route::middleware('auth:sanctum')->get('/me', fn () => request()->user());

/*
 * دخول فصل الموبايل — نفس بوابة الويب Portal\ClassroomJoinController، نفس
 * EnterClassroom بالضبط، فقط JSON بدل redirect. عمدًا خارج أي موديول: مثل
 * نظيرتها في routes/web.php، لأن EnterClassroom نفسها في app/ لا في موديول.
 */
Route::middleware('auth:sanctum')
    ->post('sessions/{session}/join', [JoinSessionController::class, 'teacher'])
    ->whereUlid('session')
    ->middleware('can:session.join')
    ->name('api.sessions.join.teacher');

/*
 * تفاصيل حصة المعلم للموبايل — مرآة routes/web.php (portal.teacher.sessions.*)
 * بنفس أسماء الصلاحيات بالضبط. مسار مختلف عمدًا عن GET /api/sessions/{session}
 * (موديول Sessions، تمثيل خام بلا أسماء) لتجنّب أي تعارض مسارات.
 */
Route::middleware('auth:sanctum')->prefix('teacher/sessions/{session}')->whereUlid('session')->group(function (): void {
    Route::get('/', [TeacherSessionController::class, 'show'])
        ->middleware('can:attendance.record')
        ->name('api.teacher.sessions.show');

    Route::post('/attendance', [TeacherSessionController::class, 'recordAttendance'])
        ->middleware('can:attendance.record')
        ->name('api.teacher.sessions.attendance.store');

    Route::post('/report', [TeacherSessionController::class, 'submitReport'])
        ->name('api.teacher.sessions.report.store');

    Route::post('/postponement-requests', [TeacherSessionController::class, 'requestPostponement'])
        ->name('api.teacher.sessions.postponement-requests.store');

    Route::post('/apology', [TeacherSessionController::class, 'apologize'])
        ->middleware('can:attendance.record')
        ->name('api.teacher.sessions.apology.store');
});

/*
 * طلبات تأجيل المعلم للموبايل — مرآة routes/web.php (portal.teacher.postponements.*).
 */
Route::middleware('auth:sanctum')->prefix('teacher/postponements')->group(function (): void {
    Route::get('/', [TeacherPostponementController::class, 'index'])
        ->middleware('can:session.postpone.approve')
        ->name('api.teacher.postponements.index');

    Route::post('/{postponement}/approve', [TeacherPostponementController::class, 'approve'])
        ->whereUlid('postponement')
        ->name('api.teacher.postponements.approve');

    Route::post('/{postponement}/propose-alternative', [TeacherPostponementController::class, 'propose'])
        ->whereUlid('postponement')
        ->name('api.teacher.postponements.propose-alternative');

    Route::post('/{postponement}/reject', [TeacherPostponementController::class, 'reject'])
        ->whereUlid('postponement')
        ->name('api.teacher.postponements.reject');
});

/*
 * ملف المعلم الشخصي للموبايل — مرآة routes/web.php (portal.teacher.profile*)،
 * نفس Actions/FormRequests بالضبط في Modules\Identity.
 */
Route::middleware('auth:sanctum')->prefix('teacher/profile')->group(function (): void {
    Route::get('/', [TeacherProfileController::class, 'show'])
        ->name('api.teacher.profile.show');

    Route::patch('/', [TeacherProfileController::class, 'update'])
        ->name('api.teacher.profile.update');

    Route::put('/password', [TeacherProfileController::class, 'password'])
        ->name('api.teacher.profile.password');
});

/*
 * أوقات توفّر المعلم للموبايل — مرآة routes/web.php (portal.teacher.availability*)،
 * نفس StoreOwnAvailabilityRequest وSetTeacherAvailability/RemoveTeacherAvailability.
 */
Route::middleware('auth:sanctum')->prefix('teacher/availability')->group(function (): void {
    Route::get('/', [TeacherAvailabilityController::class, 'index'])
        ->name('api.teacher.availability.index');

    Route::post('/', [TeacherAvailabilityController::class, 'store'])
        ->name('api.teacher.availability.store');

    Route::delete('/{availability}', [TeacherAvailabilityController::class, 'destroy'])
        ->whereUlid('availability')
        ->name('api.teacher.availability.destroy');
});

/*
 * دليل طلاب المعلم للموبايل — يوسّع المصدر ليشمل الجداول الفردية إلى جانب
 * المجموعات (انظر تعليق PortalData::teacherStudentRoster). لا مسار ويب
 * يوازيه بنفس الاتساع؛ Portal\TeacherStudentsController القديم مبني على
 * المجموعات وحدها.
 */
Route::middleware('auth:sanctum')->prefix('teacher/students')->group(function (): void {
    Route::get('/', [TeacherStudentController::class, 'index'])
        ->name('api.teacher.students.index');

    Route::get('/{student}', [TeacherStudentController::class, 'show'])
        ->whereUlid('student')
        ->name('api.teacher.students.show');
});

/*
 * بنود مطلوبة من المعلم للموبايل (حاليًا: تقارير حصص متأخرة) — يعتمد على
 * PortalData::teacherLateReportSessions نفسها التي تستخدمها
 * Portal\TeacherDashboardController، غير معدَّلة.
 */
Route::middleware('auth:sanctum')
    ->get('teacher/required-reports', [TeacherRequiredReportsController::class, 'index'])
    ->name('api.teacher.required-reports.index');

/*
 * مجموعات المعلم للموبايل — مرآة routes/web.php (portal.teacher.groups*).
 */
Route::middleware('auth:sanctum')->prefix('teacher/groups')->group(function (): void {
    Route::get('/', [TeacherGroupController::class, 'index'])
        ->name('api.teacher.groups.index');

    Route::get('/{group}', [TeacherGroupController::class, 'show'])
        ->whereUlid('group')
        ->name('api.teacher.groups.show');
});

/*
 * كشف أجر المعلم للموبايل — مرآة routes/web.php (portal.teacher.earnings)
 * بنفس الحارسين بالضبط: المسار لا يُسجَّل أصلًا حين تكون الميزة مطفأة،
 * وcan:payroll.view يُفعِّل TeacherFinancialVisibilityGate العالمي
 * (Gate::before) الذي يخفي الكشف عن معلم علّمت عليه الإدارة financials_visible=false.
 */
if ((bool) config('features.payroll')) {
    Route::middleware(['auth:sanctum', 'can:payroll.view'])
        ->get('teacher/earnings', [TeacherEarningsController::class, 'index'])
        ->name('api.teacher.earnings.index');
}

/*
 * دخول فصل الطالب للموبايل — مرآة Portal\ClassroomJoinController::student().
 */
Route::middleware('auth:sanctum')
    ->post('student/sessions/{session}/join', [JoinSessionController::class, 'student'])
    ->whereUlid('session')
    ->middleware('can:session.join')
    ->name('api.student.sessions.join');

/*
 * جدول وتفاصيل حصص الطالب للموبايل — مرآة routes/web.php
 * (portal.student.dashboard/schedule/sessions.show).
 */
Route::middleware('auth:sanctum')->prefix('student/sessions')->group(function (): void {
    Route::get('/', [StudentSessionController::class, 'index'])
        ->middleware('can:session.view')
        ->name('api.student.sessions.index');

    Route::get('/{session}', [StudentSessionController::class, 'show'])
        ->whereUlid('session')
        ->middleware('can:session.view')
        ->name('api.student.sessions.show');

    Route::post('/{session}/postponement-requests', [StudentSessionController::class, 'requestPostponement'])
        ->whereUlid('session')
        ->name('api.student.sessions.postponement-requests.store');

    Route::post('/{session}/apologies', [StudentSessionController::class, 'submitApology'])
        ->whereUlid('session')
        ->name('api.student.sessions.apologies.store');
});

/*
 * قبول الطالب لموعد بديل اقترحه المعلم لتأجيل — مرآة routes/web.php
 * (portal.student.postponements.accept-alternative).
 */
Route::middleware('auth:sanctum')
    ->post('student/postponements/{postponement}/accept-alternative', [StudentPostponementController::class, 'acceptAlternative'])
    ->whereUlid('postponement')
    ->name('api.student.postponements.accept-alternative');

/*
 * ملف الطالب الشخصي للموبايل — مرآة routes/web.php (portal.student.profile*).
 */
Route::middleware('auth:sanctum')->prefix('student/profile')->group(function (): void {
    Route::get('/', [StudentProfileController::class, 'show'])
        ->name('api.student.profile.show');

    Route::patch('/', [StudentProfileController::class, 'update'])
        ->name('api.student.profile.update');

    Route::put('/password', [StudentProfileController::class, 'password'])
        ->name('api.student.profile.password');
});

/*
 * لوحة ولي الأمر للموبايل — مرآة routes/web.php (portal.guardian.*)،
 * نفس PortalData::guardianChildren/guardianChild/upcomingStudentSessions/
 * studentSession التي تستخدمها Portal\GuardianDashboardController وأخواتها.
 */
Route::middleware('auth:sanctum')->prefix('guardian')->group(function (): void {
    Route::get('children', [GuardianController::class, 'children'])
        ->middleware('can:student.view')
        ->name('api.guardian.children.index');

    Route::get('children/{child}/sessions', [GuardianController::class, 'childSessions'])
        ->whereUlid('child')
        ->middleware('can:schedule.view')
        ->name('api.guardian.children.sessions.index');

    Route::get('children/{child}/sessions/{session}', [GuardianController::class, 'childSession'])
        ->whereUlid('child')
        ->whereUlid('session')
        ->middleware('can:schedule.view')
        ->name('api.guardian.children.sessions.show');
});
