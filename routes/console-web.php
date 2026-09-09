<?php

declare(strict_types=1);

use App\Http\Controllers\Console\DirectoryController;
use App\Http\Controllers\Console\QuranController;
use App\Http\Controllers\Console\ReportsController;
use App\Http\Controllers\Console\SessionPayController;
use App\Http\Controllers\Console\SettingsController;
use App\Http\Controllers\Console\SettingsOperationsController;
use App\Http\Controllers\Console\TeacherFinancialVisibilityController;
use App\Http\Controllers\Console\WorkspaceController;
use Illuminate\Support\Facades\Route;
use Modules\Reporting\Presentation\Http\Controllers\ExportOperationalReportPdfController;

Route::get('/', WorkspaceController::class)->name('home');
Route::inertia('/notifications', 'Console/Notifications')->name('notifications');
Route::get('/directory', DirectoryController::class)->name('directory');
Route::get('/reports', ReportsController::class)->middleware('can:report.view')->name('reports');
Route::get('/reports/pdf', ExportOperationalReportPdfController::class)->middleware(['can:report.view', 'can:report.export'])->name('reports.pdf');

Route::get('/settings', [SettingsController::class, 'index'])->middleware('can:organizations.view')->name('settings');
Route::put('/settings', [SettingsController::class, 'update'])->middleware('can:organizations.update')->name('settings.update');

Route::post('/settings/session-pay', [SessionPayController::class, 'store'])->middleware('can:organizations.manage_settings')->name('settings.session-pay');

Route::post('/settings/{operation}', SettingsOperationsController::class)
    ->whereIn('operation', ['accounts', 'notifications', 'calendar-create', 'calendar-activate', 'calendar-close', 'holiday-create', 'holiday-remove'])
    ->middleware('can:organizations.view')->name('settings.operation');

Route::get('/quran', [QuranController::class, 'index'])->middleware('can:student.view.any')->name('quran');
Route::get('/quran/availability', [QuranController::class, 'availability'])->middleware(['can:student.view.any', 'can:schedule.manage'])->name('quran.availability');
Route::post('/quran/{student}', [QuranController::class, 'store'])->whereUlid('student')->middleware(['can:student.view.any', 'can:schedule.manage'])->name('quran.store');

require __DIR__.'/console-quran.php';

require __DIR__.'/console-sessions.php';

Route::put('/teachers/{profile}/financial-visibility', TeacherFinancialVisibilityController::class)->whereUlid('profile')->middleware('can:staff.contract.update')->name('teachers.financial-visibility');
