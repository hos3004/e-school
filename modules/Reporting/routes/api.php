<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Reporting\Presentation\Http\Controllers\CorrectStudentDashboardController;
use Modules\Reporting\Presentation\Http\Controllers\ShowStudentDashboardController;
use Modules\Reporting\Presentation\Http\Controllers\StoreOrganizationSnapshotController;

/*
|--------------------------------------------------------------------------
| مسارات موديول Reporting — الـ API
|--------------------------------------------------------------------------
|
| تُحمَّل تلقائيًا من ModuleRegistry::loadRoutes() ضمن مجموعة middleware
| «api» وبالبادئة api/. كل الكتابة تمر عبر FormRequests والصلاحيات.
*/

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/student-dashboards/{enrollmentId}', ShowStudentDashboardController::class)
        ->whereUlid('enrollmentId')
        ->name('reporting.student-dashboards.show');

    Route::post('/organization-snapshots', StoreOrganizationSnapshotController::class)
        ->name('reporting.organization-snapshots.store');

    Route::post('/student-dashboards/corrections', CorrectStudentDashboardController::class)
        ->name('reporting.student-dashboards.correct');
});
