<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| مسارات موديول Content — الـ API
|--------------------------------------------------------------------------
|
| يُحمَّل هذا الملف تلقائيًا من ModuleRegistry::loadRoutes() ضمن مجموعة
| middleware «api» وبالبادئة api/ عند إقلاع التطبيق. لا مسارات معرّفة
| بعد؛ ستُضاف هنا مسارات طبقة Presentation للموديول عند بنائها.
*/

use Illuminate\Support\Facades\Route;
use Modules\Content\Presentation\Http\Controllers\DeleteCourseMaterialController;
use Modules\Content\Presentation\Http\Controllers\ListCourseMaterialsController;
use Modules\Content\Presentation\Http\Controllers\ShowCourseMaterialController;
use Modules\Content\Presentation\Http\Controllers\UpdateCourseMaterialController;
use Modules\Content\Presentation\Http\Controllers\UploadCourseMaterialController;

Route::middleware(['auth:sanctum', 'can:content.manage'])
    ->prefix('content/materials')
    ->name('content.materials.')
    ->group(function (): void {
        Route::get('/', ListCourseMaterialsController::class)->name('index');
        Route::post('/', UploadCourseMaterialController::class)->name('store');
        Route::get('/{material}', ShowCourseMaterialController::class)->name('show');
        Route::put('/{material}', UpdateCourseMaterialController::class)->name('update');
        Route::delete('/{material}', DeleteCourseMaterialController::class)->name('destroy');
    });
