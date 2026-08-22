<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| مسارات موديول Academics — الـ API
|--------------------------------------------------------------------------
|
| يُحمَّل هذا الملف تلقائيًا من ModuleRegistry::loadRoutes() ضمن مجموعة
| middleware «api» وبالبادئة api/ عند إقلاع التطبيق.
*/

use Illuminate\Support\Facades\Route;
use Modules\Academics\Presentation\Http\Controllers\ArchiveCourseController;
use Modules\Academics\Presentation\Http\Controllers\ArchiveProgramController;
use Modules\Academics\Presentation\Http\Controllers\ReorderLevelsController;
use Modules\Academics\Presentation\Http\Controllers\StoreCourseController;
use Modules\Academics\Presentation\Http\Controllers\StoreLevelController;
use Modules\Academics\Presentation\Http\Controllers\StoreProgramController;
use Modules\Academics\Presentation\Http\Controllers\UpdateCourseController;
use Modules\Academics\Presentation\Http\Controllers\UpdateLevelController;
use Modules\Academics\Presentation\Http\Controllers\UpdateProgramController;

Route::middleware(['auth:sanctum'])->group(function (): void {
    // البرامج
    Route::post('academics/programs', StoreProgramController::class)->name('academics.programs.store');
    Route::put('academics/programs/{program}', UpdateProgramController::class)->name('academics.programs.update');
    Route::delete('academics/programs/{program}', ArchiveProgramController::class)->name('academics.programs.archive');

    // المستويات
    Route::post('academics/levels', StoreLevelController::class)->name('academics.levels.store');
    Route::put('academics/levels/{level}', UpdateLevelController::class)->name('academics.levels.update');
    Route::post('academics/levels/reorder', ReorderLevelsController::class)
        ->name('academics.levels.reorder');

    // الكورسات
    Route::post('academics/courses', StoreCourseController::class)->name('academics.courses.store');
    Route::put('academics/courses/{course}', UpdateCourseController::class)->name('academics.courses.update');
    Route::delete('academics/courses/{course}', ArchiveCourseController::class)->name('academics.courses.archive');
});
