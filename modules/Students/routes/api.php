<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Students\Presentation\Http\Controllers\ArchiveStudentController;
use Modules\Students\Presentation\Http\Controllers\ListStudentProfilesController;
use Modules\Students\Presentation\Http\Controllers\RegisterStudentController;
use Modules\Students\Presentation\Http\Controllers\RestoreStudentController;
use Modules\Students\Presentation\Http\Controllers\ShowStudentProfileController;
use Modules\Students\Presentation\Http\Controllers\UpdateStudentProfileController;

/*
|--------------------------------------------------------------------------
| مسارات موديول Students — الـ API
|--------------------------------------------------------------------------
|
| تُحمَّل تلقائيًا ضمن مجموعة «api» بالبادئة api/. كل مسار يمر بسياسة
| StudentProfilePolicy عبر FormRequest أو abort_unless داخل المتحكم.
*/

Route::get('/students', ListStudentProfilesController::class)->name('students.index');
Route::post('/students', RegisterStudentController::class)->name('students.store');

Route::get('/students/{student}', ShowStudentProfileController::class)
    ->whereUlid('student')
    ->name('students.show');

Route::patch('/students/{student}', UpdateStudentProfileController::class)
    ->whereUlid('student')
    ->name('students.update');

Route::delete('/students/{student}', ArchiveStudentController::class)
    ->whereUlid('student')
    ->name('students.archive');

Route::post('/students/{student}/restore', RestoreStudentController::class)
    ->whereUlid('student')
    ->name('students.restore');
