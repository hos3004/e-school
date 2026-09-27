<?php

declare(strict_types=1);

use App\Http\Controllers\Api\JoinSessionController;
use App\Http\Controllers\Api\TeacherSessionController;
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
});
