<?php

declare(strict_types=1);

use App\Http\Controllers\Learning\LearningAvailabilityController;
use App\Http\Controllers\Learning\LearningController;
use App\Http\Controllers\Learning\LearningEntryController;
use App\Http\Controllers\Learning\LearningScheduleChangeController;
use App\Http\Controllers\Learning\LearningServicesController;
use App\Http\Controllers\Learning\LearningSessionController;
use App\Http\Controllers\Learning\LearningSessionRequestsController;
use App\Http\Controllers\Learning\ProfileWriteController;
use App\Http\Controllers\Learning\TeachingAssignmentsController;
use App\Http\Controllers\Portal\ClassroomJoinController;
use App\Http\Controllers\Portal\SessionPostponementRequestController;
use App\Http\Controllers\Portal\StudentAssignmentSubmissionController;
use App\Http\Controllers\Portal\TeacherAttendanceController;
use App\Http\Controllers\Portal\TeacherPostponementResponseController;
use App\Http\Controllers\Portal\TeacherSessionReportController;
use Illuminate\Support\Facades\Route;

// Included only when console.enabled is on, inside the authenticated web group.
Route::prefix('learn')->name('learning.')->group(function (): void {
    require __DIR__.'/learning-library.php';
    Route::get('teacher/postponements', [LearningSessionRequestsController::class, 'index'])->middleware('can:session.postpone.approve')->name('teacher.postponements');
    Route::post('{kind}/sessions/{session}/postponements', [LearningSessionRequestsController::class, 'postpone'])->whereIn('kind', ['student', 'teacher'])->whereUlid('session')->middleware('can:session.postpone.request')->name('sessions.postpone');
    Route::post('student/sessions/{session}/apology', [LearningSessionRequestsController::class, 'apologize'])->whereUlid('session')->middleware('can:session.postpone.request')->name('student.sessions.apology');
    Route::post('student/postponements/{postponement}/accept-alternative', [SessionPostponementRequestController::class, 'acceptAlternative'])->whereUlid('postponement')->middleware('can:session.postpone.request')->name('student.postponements.accept-alternative');
    Route::post('teacher/postponements/{postponement}/approve', [TeacherPostponementResponseController::class, 'approve'])->whereUlid('postponement')->middleware('can:session.postpone.approve')->name('teacher.postponements.approve');
    Route::post('teacher/postponements/{postponement}/propose', [LearningSessionRequestsController::class, 'propose'])->whereUlid('postponement')->middleware('can:session.postpone.approve')->name('teacher.postponements.propose');
    Route::post('teacher/postponements/{postponement}/reject', [LearningSessionRequestsController::class, 'reject'])->whereUlid('postponement')->middleware('can:session.postpone.approve')->name('teacher.postponements.reject');
    Route::post('teacher/schedules/{schedule}/change-requests', [LearningScheduleChangeController::class, 'store'])->whereUlid('schedule')->middleware('can:schedule.change.request')->name('teacher.schedule-changes.store');
    Route::post('teacher/schedule-changes/{change}/withdraw', [LearningScheduleChangeController::class, 'withdraw'])->whereUlid('change')->middleware('can:schedule.change.request')->name('teacher.schedule-changes.withdraw');
    Route::post('student/schedule-changes/{change}/respond', [LearningScheduleChangeController::class, 'respond'])->whereUlid('change')->middleware('can:schedule.change.respond')->name('student.schedule-changes.respond');
    Route::get('teacher/availability', [LearningAvailabilityController::class, 'index'])->middleware('can:staff.view')->name('teacher.availability');
    Route::post('teacher/availability', [LearningAvailabilityController::class, 'store'])->middleware('can:staff.availability.create')->name('teacher.availability.store');
    Route::delete('teacher/availability/{availability}', [LearningAvailabilityController::class, 'destroy'])->whereUlid('availability')->middleware('can:staff.view')->name('teacher.availability.destroy');
    Route::get('{kind}/schedule', [LearningServicesController::class, 'schedule'])->whereIn('kind', ['student', 'teacher'])->middleware('can:schedule.view')->name('schedule');
    Route::get('{kind}/notifications', [LearningServicesController::class, 'notifications'])->whereIn('kind', ['student', 'teacher'])->name('notifications');
    Route::get('student/reports', [LearningServicesController::class, 'reports'])->middleware('can:session_report.view')->name('student.reports');
    Route::get('teacher/earnings', [LearningServicesController::class, 'earnings'])->middleware('can:payroll.view')->name('teacher.earnings');
    Route::get('teacher/assignments/list', [TeachingAssignmentsController::class, 'listing'])->name('teacher.assignments.list');
    Route::get('teacher/assignments/{assignment}', [TeachingAssignmentsController::class, 'show'])->whereUlid('assignment')->name('teacher.assignments.show');
    Route::get('teacher/assignments', [TeachingAssignmentsController::class, 'index'])->name('teacher.assignments');
    Route::post('teacher/assignments', [TeachingAssignmentsController::class, 'store'])->middleware('can:assignment.manage')->name('teacher.assignments.store');
    Route::post('student/assignments/{assignment}/submit', StudentAssignmentSubmissionController::class)->whereUlid('assignment')->middleware('can:assignment.submit')->name('student.assignments.submit');
    Route::get('entry', LearningEntryController::class)->name('entry');
    Route::get('student', [LearningController::class, 'student'])->middleware('can:session.view')->name('student.dashboard');
    Route::get('teacher', [LearningController::class, 'teacher'])->middleware('can:session.view')->name('teacher.dashboard');
    Route::get('teacher/students/{student}', [LearningController::class, 'teacherStudent'])->whereUlid('student')->middleware('can:student.view')->name('teacher.students.show');
    foreach (['student', 'teacher'] as $kind) {
        Route::get($kind.'/profile', [LearningController::class, $kind.'Profile'])->name($kind.'.profile');
        Route::patch($kind.'/profile', [ProfileWriteController::class, 'update'])->name($kind.'.profile.update');
        Route::put($kind.'/profile/password', [ProfileWriteController::class, 'password'])->name($kind.'.profile.password');
        Route::get($kind.'/sessions/{session}', [LearningSessionController::class, $kind])->whereUlid('session')
            ->middleware('can:'.($kind === 'teacher' ? 'attendance.record' : 'session.view'))->name($kind.'.sessions.show');
        Route::get($kind.'/sessions/{session}/join', [ClassroomJoinController::class, $kind])->whereUlid('session')
            ->middleware('can:session.join')->name($kind.'.sessions.join');
    }
    Route::post('teacher/sessions/{session}/attendance', TeacherAttendanceController::class)->whereUlid('session')
        ->middleware('can:attendance.record')->name('teacher.sessions.attendance.store');
    Route::post('teacher/sessions/{session}/report', TeacherSessionReportController::class)->whereUlid('session')
        ->middleware('can:session_report.create')->name('teacher.sessions.report.store');
});
