<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Attendance\Presentation\Http\Controllers\ConfirmAttendanceController;
use Modules\Attendance\Presentation\Http\Controllers\ListAttendancesController;
use Modules\Attendance\Presentation\Http\Controllers\OverrideAttendanceController;
use Modules\Attendance\Presentation\Http\Controllers\RecordAttendanceController;
use Modules\Attendance\Presentation\Http\Controllers\ShowAttendanceController;

/*
|--------------------------------------------------------------------------
| مسارات موديول Attendance — الـ API
|--------------------------------------------------------------------------
|
| تُحمَّل تلقائيًا ضمن مجموعة «api» بالبادئة api/. كل مسار يمر بسياسة
| AttendancePolicy عبر FormRequest أو abort_unless داخل المتحكم.
*/

Route::get('/attendances', ListAttendancesController::class)->name('attendance.index');

Route::post('/attendances', RecordAttendanceController::class)->name('attendance.record');

Route::get('/attendances/{attendance}', ShowAttendanceController::class)
    ->whereUlid('attendance')
    ->name('attendance.show');

Route::patch('/attendances/{attendance}', OverrideAttendanceController::class)
    ->whereUlid('attendance')
    ->name('attendance.override');

Route::post('/attendances/{attendance}/confirm', ConfirmAttendanceController::class)
    ->whereUlid('attendance')
    ->name('attendance.confirm');
