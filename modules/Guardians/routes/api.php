<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| مسارات موديول Guardians — الـ API
|--------------------------------------------------------------------------
|
| يُحمَّل هذا الملف تلقائيًا من ModuleRegistry::loadRoutes() ضمن مجموعة
| middleware «api» وبالبادئة api/ عند إقلاع التطبيق.
*/

use Illuminate\Support\Facades\Route;
use Modules\Guardians\Presentation\Http\Controllers\ArchiveGuardianProfileController;
use Modules\Guardians\Presentation\Http\Controllers\LinkStudentController;
use Modules\Guardians\Presentation\Http\Controllers\ListGuardianLinksController;
use Modules\Guardians\Presentation\Http\Controllers\SetPrimaryGuardianLinkController;
use Modules\Guardians\Presentation\Http\Controllers\StoreGuardianProfileController;
use Modules\Guardians\Presentation\Http\Controllers\UnlinkStudentController;
use Modules\Guardians\Presentation\Http\Controllers\UpdateGuardianLinkController;
use Modules\Guardians\Presentation\Http\Controllers\UpdateGuardianProfileController;
use Modules\Guardians\Presentation\Http\Controllers\VerifyGuardianLinkController;

Route::middleware(['auth'])->group(function (): void {
    // ملفات الأوصياء
    Route::post('guardians/profiles', StoreGuardianProfileController::class)
        ->name('guardians.profiles.store');
    Route::patch('guardians/profiles/{guardian_profile}', UpdateGuardianProfileController::class)
        ->name('guardians.profiles.update');
    Route::delete('guardians/profiles/{guardian_profile}', ArchiveGuardianProfileController::class)
        ->name('guardians.profiles.archive');

    // الروابط
    Route::post('guardians/profiles/{guardian_profile}/students', LinkStudentController::class)
        ->name('guardians.links.store');
    Route::get('guardians/links', ListGuardianLinksController::class)
        ->name('guardians.links.index');
    Route::patch('guardians/links/{guardian_link}', UpdateGuardianLinkController::class)
        ->name('guardians.links.update');
    Route::post('guardians/links/{guardian_link}/verify', VerifyGuardianLinkController::class)
        ->name('guardians.links.verify');
    Route::post('guardians/links/{guardian_link}/primary', SetPrimaryGuardianLinkController::class)
        ->name('guardians.links.set-primary');
    Route::delete('guardians/links/{guardian_link}', UnlinkStudentController::class)
        ->name('guardians.links.destroy');
});
