<?php

declare(strict_types=1);

use App\Http\Controllers\Console\ProgramSessionReportsController;
use App\Http\Controllers\Console\ProgramSessionReportSettingsController;
use Illuminate\Support\Facades\Route;

Route::prefix('reports/session-reports')->name('reports.session-reports.')->group(function (): void {
    Route::get('/', [ProgramSessionReportsController::class, 'index'])
        ->middleware('can:report.view')
        ->name('index');

    Route::get('/programs/{program}', [ProgramSessionReportsController::class, 'program'])
        ->whereUlid('program')
        ->middleware('can:report.view')
        ->name('program');

    Route::get('/settings', [ProgramSessionReportSettingsController::class, 'edit'])
        ->middleware('can:reporting.settings.manage')
        ->name('settings.edit');

    Route::post('/settings', [ProgramSessionReportSettingsController::class, 'update'])
        ->middleware('can:reporting.settings.manage')
        ->name('settings.update');
});
