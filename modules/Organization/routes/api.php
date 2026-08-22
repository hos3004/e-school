<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| مسارات موديول Organization — الـ API
|--------------------------------------------------------------------------
|
| يُحمَّل هذا الملف تلقائيًا من ModuleRegistry::loadRoutes() ضمن مجموعة
| middleware «api» وبالبادئة api/ عند إقلاع التطبيق.
*/

use Illuminate\Support\Facades\Route;
use Modules\Organization\Presentation\Http\Controllers\ActivateAcademicCalendarController;
use Modules\Organization\Presentation\Http\Controllers\CloseAcademicCalendarController;
use Modules\Organization\Presentation\Http\Controllers\DeleteHolidayController;
use Modules\Organization\Presentation\Http\Controllers\ListAcademicCalendarsController;
use Modules\Organization\Presentation\Http\Controllers\ListHolidaysController;
use Modules\Organization\Presentation\Http\Controllers\ShowOrganizationController;
use Modules\Organization\Presentation\Http\Controllers\StoreAcademicCalendarController;
use Modules\Organization\Presentation\Http\Controllers\StoreHolidayController;
use Modules\Organization\Presentation\Http\Controllers\StoreOrganizationController;
use Modules\Organization\Presentation\Http\Controllers\UpdateOrganizationController;
use Modules\Organization\Presentation\Http\Controllers\UpdateOrganizationSettingController;

Route::middleware(['auth:sanctum'])->group(function (): void {
    Route::post('organizations', StoreOrganizationController::class)
        ->name('organizations.store');
    Route::get('organizations/{organization}', ShowOrganizationController::class)
        ->name('organizations.show');
    Route::patch('organizations/{organization}', UpdateOrganizationController::class)
        ->name('organizations.update');

    Route::patch('organizations/{organization}/settings', UpdateOrganizationSettingController::class)
        ->name('organizations.settings.update');

    Route::get('organizations/{organization}/academic-calendars', ListAcademicCalendarsController::class)
        ->name('organizations.calendars.index');
    Route::post('organizations/{organization}/academic-calendars', StoreAcademicCalendarController::class)
        ->name('organizations.calendars.store');

    Route::patch('academic-calendars/{calendar}/activate', ActivateAcademicCalendarController::class)
        ->name('academic-calendars.activate');
    Route::patch('academic-calendars/{calendar}/close', CloseAcademicCalendarController::class)
        ->name('academic-calendars.close');

    Route::get('organizations/{organization}/holidays', ListHolidaysController::class)
        ->name('organizations.holidays.index');
    Route::post('organizations/{organization}/holidays', StoreHolidayController::class)
        ->name('organizations.holidays.store');
    Route::delete('holidays/{holiday}', DeleteHolidayController::class)
        ->name('holidays.destroy');
});
