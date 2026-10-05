<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Reporting\Presentation\Http\Controllers\ExportOperationalReportPdfController;
use Modules\Reporting\Presentation\Http\Controllers\ShowStudentDashboardController;

/*
|--------------------------------------------------------------------------
| مسارات موديول Reporting — الويب
|--------------------------------------------------------------------------
|
| لا صفحات ويب مستقلة للموديول حاليًا؛ اللوحات تُدار من Filament والقراءة
| عبر الـ API. المسار التالي يوجّه عرض اللوحة إلى نفس المتحكم الرقيق.
*/

Route::middleware(['web', 'auth'])
    ->get('/reporting/student-dashboards/{enrollmentId}', ShowStudentDashboardController::class)
    ->whereUlid('enrollmentId')
    ->name('reporting.web.student-dashboards.show');

Route::middleware(['web', 'auth', 'can:report.export'])
    ->get('/reporting/operational-reports/export.pdf', ExportOperationalReportPdfController::class)
    ->name('reporting.operational.export-pdf');
