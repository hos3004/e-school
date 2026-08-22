<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Students\Presentation\Http\Controllers\AcceptRegistrationApplicationController;
use Modules\Students\Presentation\Http\Controllers\ArchiveStudentController;
use Modules\Students\Presentation\Http\Controllers\ListRegistrationApplicationsController;
use Modules\Students\Presentation\Http\Controllers\ListStudentProfilesController;
use Modules\Students\Presentation\Http\Controllers\RejectRegistrationApplicationController;
use Modules\Students\Presentation\Http\Controllers\RestoreStudentController;
use Modules\Students\Presentation\Http\Controllers\ReviewRegistrationApplicationController;
use Modules\Students\Presentation\Http\Controllers\ShowRegistrationApplicationController;
use Modules\Students\Presentation\Http\Controllers\ShowStudentProfileController;
use Modules\Students\Presentation\Http\Controllers\StoreRegistrationApplicationController;
use Modules\Students\Presentation\Http\Controllers\SubmitRegistrationApplicationController;
use Modules\Students\Presentation\Http\Controllers\UpdateStudentProfileController;

/*
|--------------------------------------------------------------------------
| مسارات موديول Students — الـ API
|--------------------------------------------------------------------------
|
| تُحمَّل تلقائيًا ضمن مجموعة «api» بالبادئة api/. كل مسار يمر بسياسة
| StudentProfilePolicy عبر FormRequest أو abort_unless داخل المتحكم.
*/

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/students', ListStudentProfilesController::class)->name('students.index');

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
});

Route::middleware('auth:sanctum')->prefix('registration-applications')->group(function (): void {
    Route::get('/', ListRegistrationApplicationsController::class)
        ->name('registration-applications.index');
    Route::post('/', StoreRegistrationApplicationController::class)
        ->name('registration-applications.store');
    Route::get('/{registrationApplication}', ShowRegistrationApplicationController::class)
        ->whereUlid('registrationApplication')
        ->name('registration-applications.show');
    Route::post('/{registrationApplication}/submit', SubmitRegistrationApplicationController::class)
        ->whereUlid('registrationApplication')
        ->name('registration-applications.submit');
    Route::post('/{registrationApplication}/review', ReviewRegistrationApplicationController::class)
        ->whereUlid('registrationApplication')
        ->name('registration-applications.review');
    Route::post('/{registrationApplication}/accept', AcceptRegistrationApplicationController::class)
        ->whereUlid('registrationApplication')
        ->name('registration-applications.accept');
    Route::post('/{registrationApplication}/reject', RejectRegistrationApplicationController::class)
        ->whereUlid('registrationApplication')
        ->name('registration-applications.reject');
});
