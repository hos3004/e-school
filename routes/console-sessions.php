<?php

declare(strict_types=1);

use App\Http\Controllers\Console\GroupScheduleController;
use App\Http\Controllers\Console\SessionReportController;
use App\Http\Controllers\Console\SessionReviewController;
use App\Http\Controllers\Console\SessionsController;
use Illuminate\Support\Facades\Route;

Route::get('/sessions', SessionsController::class)->middleware(['can:session.view', 'can:student.view.any'])->name('sessions');

/*
 * اعتماد الحصص: مسار ثابت، فيُسجَّل قبل أي مسار يلتقط {session} حتى لا تُقرأ
 * كلمة review معرّفًا لحصة.
 */
Route::get('/sessions/review', [SessionReviewController::class, 'index'])
    ->middleware(['can:session.view', 'can:session.finalize'])
    ->name('sessions.review');
Route::post('/sessions/{session}/review', [SessionReviewController::class, 'finalize'])
    ->whereUlid('session')
    ->middleware('can:session.view')
    ->name('sessions.review.decide');
Route::get('/sessions/{session}/report', SessionReportController::class)
    ->whereUlid('session')
    ->middleware(['can:session.view', 'can:report.view'])
    ->name('sessions.report');
Route::middleware('can:schedule.manage')->group(function (): void {
    Route::get('/schedules/create', [GroupScheduleController::class, 'create'])->name('schedules.create');
    Route::get('/schedules/availability', [GroupScheduleController::class, 'availability'])->name('schedules.availability');
    Route::post('/schedules', [GroupScheduleController::class, 'store'])->name('schedules.store');
    Route::get('/schedules/{schedule}/edit', [GroupScheduleController::class, 'edit'])->whereUlid('schedule')->name('schedules.edit');
    Route::patch('/schedules/{schedule}', [GroupScheduleController::class, 'update'])->whereUlid('schedule')->name('schedules.update');
});
