<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| مسارات موديول AcademicReports — الـ API
|--------------------------------------------------------------------------
|
| يُحمَّل هذا الملف تلقائيًا من ModuleRegistry::loadRoutes() ضمن مجموعة
| middleware «api» وبالبادئة api/ عند إقلاع التطبيق. كل مسار كتابة يمرّ
| عبر FormRequest الذي يستدعي سياسة الموديول.
*/

use Illuminate\Support\Facades\Route;
use Modules\AcademicReports\Presentation\Http\Controllers\ApproveMonthlyReportController;
use Modules\AcademicReports\Presentation\Http\Controllers\ListMonthlyReportsController;
use Modules\AcademicReports\Presentation\Http\Controllers\ListSessionReportsController;
use Modules\AcademicReports\Presentation\Http\Controllers\SendMonthlyReportController;
use Modules\AcademicReports\Presentation\Http\Controllers\ShowMonthlyReportController;
use Modules\AcademicReports\Presentation\Http\Controllers\ShowSessionReportController;
use Modules\AcademicReports\Presentation\Http\Controllers\StoreMonthlyReportController;
use Modules\AcademicReports\Presentation\Http\Controllers\SubmitSessionReportController;

Route::middleware('auth')->group(function (): void {
    Route::post('/session-reports', SubmitSessionReportController::class)
        ->whereUlid('report')
        ->name('academicreports.session_reports.store');
    Route::get('/session-reports', ListSessionReportsController::class)
        ->name('academicreports.session_reports.index');
    Route::get('/session-reports/{report}', ShowSessionReportController::class)
        ->whereUlid('report')
        ->name('academicreports.session_reports.show');

    Route::post('/monthly-reports', StoreMonthlyReportController::class)
        ->name('academicreports.monthly_reports.store');
    Route::get('/monthly-reports', ListMonthlyReportsController::class)
        ->name('academicreports.monthly_reports.index');
    Route::get('/monthly-reports/{report}', ShowMonthlyReportController::class)
        ->whereUlid('report')
        ->name('academicreports.monthly_reports.show');
    Route::patch('/monthly-reports/{report}/approve', ApproveMonthlyReportController::class)
        ->whereUlid('report')
        ->name('academicreports.monthly_reports.approve');
    Route::patch('/monthly-reports/{report}/send', SendMonthlyReportController::class)
        ->whereUlid('report')
        ->name('academicreports.monthly_reports.send');
});
