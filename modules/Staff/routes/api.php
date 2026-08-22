<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| مسارات موديول Staff — الـ API
|--------------------------------------------------------------------------
|
| يُحمَّل هذا الملف تلقائيًا من ModuleRegistry::loadRoutes() ضمن مجموعة
| middleware «api» وبالبادئة api/ عند إقلاع التطبيق.
*/

use Illuminate\Support\Facades\Route;
use Modules\Staff\Presentation\Http\Controllers\DecideTeacherLeaveController;
use Modules\Staff\Presentation\Http\Controllers\IndexStaffProfilesController;
use Modules\Staff\Presentation\Http\Controllers\IndexTeacherLeavesController;
use Modules\Staff\Presentation\Http\Controllers\ShowStaffProfileController;
use Modules\Staff\Presentation\Http\Controllers\StoreStaffProfileController;
use Modules\Staff\Presentation\Http\Controllers\StoreTeacherAvailabilityController;
use Modules\Staff\Presentation\Http\Controllers\StoreTeacherContractController;
use Modules\Staff\Presentation\Http\Controllers\StoreTeacherLeaveController;
use Modules\Staff\Presentation\Http\Controllers\StoreTeacherRateController;
use Modules\Staff\Presentation\Http\Controllers\TerminateStaffProfileController;

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('staff/profiles', IndexStaffProfilesController::class)->name('staff.profiles.index');
    Route::post('staff/profiles', StoreStaffProfileController::class)->name('staff.profiles.store');
    Route::get('staff/profiles/{profile}', ShowStaffProfileController::class)->name('staff.profiles.show');
    Route::post('staff/profiles/{profile}/terminate', TerminateStaffProfileController::class)->name('staff.profiles.terminate');

    Route::post('staff/contracts', StoreTeacherContractController::class)->name('staff.contracts.store');
    Route::post('staff/contracts/{contract}/rates', StoreTeacherRateController::class)->name('staff.rates.store');

    Route::get('staff/leaves', IndexTeacherLeavesController::class)->name('staff.leaves.index');
    Route::post('staff/leaves', StoreTeacherLeaveController::class)->name('staff.leaves.store');
    Route::post('staff/leaves/{leave}/decision', DecideTeacherLeaveController::class)->name('staff.leaves.decide');

    Route::post('staff/availability', StoreTeacherAvailabilityController::class)->name('staff.availability.store');
});
